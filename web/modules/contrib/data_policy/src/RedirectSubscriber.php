<?php

namespace Drupal\data_policy;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Link;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Routing\RedirectDestinationInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\data_policy\Entity\UserConsentInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Class RedirectSubscriber.
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
   */
  public function __construct(RouteMatchInterface $route_match, RedirectDestinationInterface $destination, AccountProxyInterface $current_user, ConfigFactoryInterface $config_factory, EntityTypeManagerInterface $entity_type_manager, MessengerInterface $messenger, DataPolicyConsentManagerInterface $data_policy_manager) {
    $this->routeMatch = $route_match;
    $this->destination = $destination;
    $this->currentUser = $current_user;
    $this->configFactory = $config_factory;
    $this->entityTypeManager = $entity_type_manager;
    $this->messenger = $messenger;
    $this->dataPolicyConsentManager = $data_policy_manager;
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
  public function checkForRedirection(GetResponseEvent $event) {
    if (!$this->dataPolicyConsentManager->isDataPolicy()) {
      return;
    }

    $route_name = $this->routeMatch->getRouteName();

    if ($route_name == 'data_policy.data_policy.agreement') {
      return;
    }

    if ($this->currentUser->hasPermission('without consent')) {
      return;
    }

    $config = $this->configFactory->get('data_policy.data_policy');

    $entity_id = $config->get('entity_id');

    /** @var \Drupal\data_policy\DataPolicyStorageInterface $data_policy_storage */
    $data_policy_storage = $this->entityTypeManager->getStorage('data_policy');

    /** @var \Drupal\data_policy\Entity\DataPolicyInterface $data_policy */
    $data_policy = $data_policy_storage->load($entity_id);

    foreach ($data_policy_storage->revisionIds($data_policy) as $vid) {
      if ($data_policy_storage->loadRevision($vid)->isDefaultRevision()) {
        break;
      }
    }

    $values = [
      'user_id' => $this->currentUser->id(),
      'data_policy_revision_id' => $vid,
    ];

    if ($enforce_consent = !empty($config->get('enforce_consent'))) {
      $values['state'] = UserConsentInterface::STATE_AGREE;
    }

    $user_consents = $this->entityTypeManager->getStorage('user_consent')
      ->loadByProperties($values);

    if (!empty($user_consents)) {
      return;
    }

    if (!$enforce_consent) {
      $link = Link::createFromRoute($this->t('here'), 'data_policy.data_policy.agreement');

      $this->messenger->addStatus($this->t('We published a new version of the data policy. You can review the data policy @url.', [
        '@url' => $link->toString(),
      ]));

      return;
    }

    $route_names = [
      'entity.user.cancel_form',
      'data_policy.data_policy',
      'system.404',
      'system.batch_page.html',
      'system.batch_page.json',
      'user.cancel_confirm',
      'user.logout',
    ];

    if (in_array($route_name, $route_names)) {
      return;
    }

    $url = Url::fromRoute('data_policy.data_policy.agreement', [], [
      'query' => $this->destination->getAsArray(),
    ]);

    $response = new RedirectResponse($url->toString());
    $event->setResponse($response);
  }

}
