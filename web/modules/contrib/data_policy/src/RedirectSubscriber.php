<?php

namespace Drupal\data_policy;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Link;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Routing\RedirectDestinationInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\data_policy\Entity\DataPolicyInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Redirection subscriber.
 *
 * @package Drupal\data_policy
 */
class RedirectSubscriber implements EventSubscriberInterface {

  use StringTranslationTrait;

  /**
   * The current active route match object.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $routeMatch;

  /**
   * The redirect destination helper.
   *
   * @var \Drupal\Core\Routing\RedirectDestinationInterface
   */
  protected $destination;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * The configuration factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The messenger.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * The Data Policy consent manager.
   *
   * @var \Drupal\data_policy\DataPolicyConsentManagerInterface
   */
  protected $dataPolicyConsentManager;

  /**
   * The module handler interface.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The database.
   *
   * @var \Drupal\Core\Database\Connection
   */
  public $database;

  /**
   * RedirectSubscriber constructor.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The current active route match object.
   * @param \Drupal\Core\Routing\RedirectDestinationInterface $destination
   *   The redirect destination helper.
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   The current user.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The configuration factory.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger.
   * @param \Drupal\data_policy\DataPolicyConsentManagerInterface $data_policy_manager
   *   The Data Policy consent manager.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler interface.
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   */
  public function __construct(
    RouteMatchInterface $route_match,
    RedirectDestinationInterface $destination,
    AccountProxyInterface $current_user,
    ConfigFactoryInterface $config_factory,
    EntityTypeManagerInterface $entity_type_manager,
    MessengerInterface $messenger,
    DataPolicyConsentManagerInterface $data_policy_manager,
    ModuleHandlerInterface $module_handler,
    Connection $database
  ) {
    $this->routeMatch = $route_match;
    $this->destination = $destination;
    $this->currentUser = $current_user;
    $this->configFactory = $config_factory;
    $this->entityTypeManager = $entity_type_manager;
    $this->messenger = $messenger;
    $this->dataPolicyConsentManager = $data_policy_manager;
    $this->moduleHandler = $module_handler;
    $this->database = $database;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[KernelEvents::REQUEST][] = ['checkForRedirection', '28'];
    return $events;
  }

  /**
   * This method is called when the KernelEvents::REQUEST event is dispatched.
   *
   * @param \Symfony\Component\HttpKernel\Event\GetResponseEvent $event
   *   The event.
   */
  public function checkForRedirection(GetResponseEvent $event): void {
    // Check if a data policy is set.
    if (!$this->dataPolicyConsentManager->isDataPolicy()) {
      return;
    }

    // Check if the current route is the data policy agreement page.
    if (($route_name = $this->routeMatch->getRouteName()) === 'data_policy.data_policy.agreement') {
      // The current route is the data policy agreement page. We don't need
      // a redirect response.
      return;
    }

    $route_names = [
      'entity.user.cancel_form',
      'data_policy.data_policy',
      'system.403',
      'system.404',
      'system.batch_page.html',
      'system.batch_page.json',
      'user.cancel_confirm',
      'user.logout',
      'entity_sanitizer_image_fallback.generator',
    ];

    if (in_array($route_name, $route_names, TRUE)) {
      return;
    }

    if ($this->currentUser->hasPermission('without consent')) {
      return;
    }

    // Check if entity tokens exist in the consent text in the settings form.
    $entity_ids = $this->dataPolicyConsentManager->getEntityIdsFromConsentText();
    if (empty($entity_ids)) {
      return;
    }

    // At least one data policy entity should exist.
    if (empty($this->entityTypeManager->getStorage('data_policy')->getQuery()->execute())) {
      return;
    }

    $existing_user_consents = $this->dataPolicyConsentManager->getExistingUserConsents($this->currentUser->id());
    $is_required_id = $this->dataPolicyConsentManager->isRequiredEntityInEntities($entity_ids);

    // Do redirect if the user did not submit any consent and there is some
    // required consent.
    if (empty($existing_user_consents) && $is_required_id) {
      $this->doRedirect($event);
      return;
    }
    elseif (empty($existing_user_consents) && !$is_required_id) {
      $this->addStatusLink();
      return;
    }

    $revisions = $this->dataPolicyConsentManager->getRevisionsByEntityIds($entity_ids);
    foreach ($revisions as $revision) {
      /** @var \Drupal\data_policy\Entity\DataPolicy $revision */
      $saved_revision_ids[] = $revision->getRevisionId();
    }

    // If a new data policy was created then we should display a link or do
    // redirect to the agreement page.
    $user_revisions = $this->getActiveUserRevisionData();

    $do_redirect = FALSE;
    $add_status_link = FALSE;
    foreach ($user_revisions as $item) {
      if (!in_array($item['data_policy_revision_id_value'], $saved_revision_ids)) {
        if ($item['required'] === '1') {
          $do_redirect = TRUE;
        }
        else {
          $add_status_link = TRUE;
        }
      }
    }

    if ($do_redirect) {
      $this->doRedirect($event);
      return;
    }
    if ($add_status_link) {
      $this->addStatusLink();
      return;
    }

    $existing_revisions = array_column($user_revisions, 'data_policy_revision_id_value');
    $revision_ids_from_consent_text = array_map(function (DataPolicyInterface $revision) {
      return $revision->getRevisionId();
    }, $revisions);
    $diff = array_diff($existing_revisions, $revision_ids_from_consent_text);
    $is_new_consents = array_diff($revision_ids_from_consent_text, $existing_revisions);

    if (empty($diff) && empty($this->getActiveUserRevisionData(TRUE)
      ->condition('state', 0)
      ->execute()
      ->fetchAll()) && empty($is_new_consents)) {
      return;
    }

    // If new consent is created then if this consent is required redirect
    // to the agreement page if not then appear status link.
    if (empty($diff) && !empty($is_new_consents)) {
      $is_new_required = $this->dataPolicyConsentManager->isRequiredEntityInEntities($is_new_consents);

      if ($is_new_required) {
        $this->doRedirect($event);
        return;
      }
      else {
        $this->addStatusLink();
        return;
      }
    }

    if ($is_required_id === FALSE) {
      $this->addStatusLink();
      return;
    }

    $this->doRedirect($event);
  }

  /**
   * Do redirect to the agreement page.
   *
   * @param \Symfony\Component\HttpKernel\Event\GetResponseEvent $event
   *   The event.
   */
  private function doRedirect(GetResponseEvent $event): void {
    // Set the destination that redirects the user after accepting the
    // data policy agreements.
    $destination = $this->getDestination();

    // Check if there are hooks to invoke that do an override.
    $implementations = $this->moduleHandler->getImplementations('data_policy_destination_alter');

    if (!empty($implementations)) {
      $module = end($implementations);
      $destination = $this->moduleHandler->invoke($module, 'data_policy_destination_alter', [
        $this->currentUser,
        $this->getDestination(),
      ]);
    }

    $url = Url::fromRoute('data_policy.data_policy.agreement', [], [
      'query' => $destination->getAsArray(),
    ]);

    $response = new RedirectResponse($url->toString());
    $event->setResponse($response);
  }

  /**
   * Add the status link.
   */
  private function addStatusLink() {
    if ($this->routeMatch->getRouteName() !== 'data_policy.data_policy.agreement') {
      $link = Link::createFromRoute($this->t('here'), 'data_policy.data_policy.agreement');
      $this->messenger->addStatus($this->t('We published a new version of the data policy. You can review the data policy @url.', [
        '@url' => $link->toString(),
      ]));
    }
  }

  /**
   * Get the redirect destination.
   *
   * @return \Drupal\Core\Routing\RedirectDestinationInterface
   *   The redirect destination.
   */
  protected function getDestination(): RedirectDestinationInterface {
    return $this->destination;
  }

  /**
   * Get active user revision data from the database.
   *
   * @param bool $return_query
   *   True if the query should re returned instead of query result.
   *
   * @return \Drupal\Core\Database\Query\SelectInterface|array
   *   Active users.
   */
  private function getActiveUserRevisionData($return_query = FALSE) {
    $query = $this->database->select('user_consent', 'uc');
    $query->condition('status', TRUE);
    $query->condition('user_id', $this->currentUser->id());
    $query->join('user_consent__data_policy_revision_id', 'ucr', 'uc.id = ucr.entity_id');
    $query->addField('uc', 'required');
    $query->addField('ucr', 'data_policy_revision_id_value');

    if ($return_query) {
      return $query;
    }

    return $query->execute()->fetchAll(\PDO::FETCH_ASSOC);
  }

}
