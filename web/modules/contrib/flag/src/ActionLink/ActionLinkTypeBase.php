<?php

namespace Drupal\flag\ActionLink;

use Drupal\Component\Plugin\PluginBase;
use Drupal\Core\Url;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\flag\FlagInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a base class for all link types.
 *
 * Link types perform two key functions within Flag: They specify the route to
 * use when a flag link is clicked, and generate the render array to display
 * flag links.
 */
abstract class ActionLinkTypeBase extends PluginBase implements ActionLinkTypePluginInterface, ContainerFactoryPluginInterface {

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  use StringTranslationTrait;

  /**
   * Build a new link type instance and sets the configuration.
   *
   * @param array $configuration
   *   The configuration array with which to initialize this plugin.
   * @param string $plugin_id
   *   The ID with which to initialize this plugin.
   * @param array $plugin_definition
   *   The plugin definition array.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user.
   */
  public function __construct(array $configuration, $plugin_id, array $plugin_definition, AccountInterface $current_user) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->configuration += $this->defaultConfiguration();
    $this->currentUser = $current_user;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('current_user')
    );
  }

  /**
   * Returns a route name given an $action.
   *
   * @param string|null $action
   *   A string containing the action name.
   *
   * @return string
   *   A string containing a route name.
   */
  abstract public function routeName($action = NULL);

  /**
   * {@inheritdoc}
   */
  public function getLinkURL($action, FlagInterface $flag, EntityInterface $entity) {
    $parameters = [
      'flag' => $flag->id(),
      'entity_id' => $entity->id(),
    ];

    return new Url($this->routeName($action), $parameters);
  }

  /**
   * Helper method to generate a destination URL parameter.
   *
   * @return string
   *  A string containing a destination URL parameter.
   */
  protected function getDestination() {
    $current_url = Url::fromRoute('<current>');
    $route_params = $current_url->getRouteParameters();

    if (isset($route_params['destination'])) {
      return $route_params['destination'];
    }

    return $current_url->getInternalPath();
  }

  /**
   * {@inheritdoc}
   */
  public function buildLink($action, FlagInterface $flag, EntityInterface $entity) {
    // Get the Flag URL.
    $url = $this->getLinkURL($action, $flag, $entity);

    $url->setRouteParameter('destination', $this->getDestination());

    $render = [];
    $render['#flag'] = $flag;
    $render['#flaggable'] = $entity;
    $render['#theme'] = 'flag';

    // Build the URL. It is important that bubbleable metadata is explicitly
    // collected and applied to the render array, as it might be rendered on its
    // own, for example in an ajax response. Specifically, this is necessary for
    // CSRF token placeholder replacements.
    $rendered_url = $url->toString(TRUE);
    $rendered_url->applyTo($render);

    $render['#attributes']['href'] = $rendered_url->getGeneratedUrl();

    if ($action === 'unflag') {
      $render['#title'] = $flag->getUnflagShortText();
      $render['#attributes']['title'] = $flag->getUnflagLongText();
      $render['#action'] = 'unflag';
    }
    else {
      $render['#title'] = $flag->getFlagShortText();
      $render['#attributes']['title'] = $flag->getFlagLongText();
      $render['#action'] = 'flag';
    }

    return $render;
  }

  /**
   * {@inheritdoc}
   */
  public function getLink(FlagInterface $flag, EntityInterface $entity) {
    $action = $flag->isFlagged($entity) ? 'unflag' : 'flag';

    $access = $flag->actionAccess($action, $this->currentUser, $entity);
    if ($access->isAllowed()) {
      // The actual render array must be in a nested key, due to a bug in
      // lazy builder handling that does not properly render top-level #type
      // elements.build
      $link = ['link' => $this->buildLink($action, $flag, $entity)];
    } else {
      $link = [];
    }

    CacheableMetadata::createFromRenderArray($link)
      ->addCacheableDependency($access)
      ->applyTo($link);

    return $link;
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies() {
    return [];
  }

  /**
   * Provides a form array for the action link plugin's settings form.
   *
   * Derived classes will want to override this method.
   *
   * @param array $form
   *   The form array.
   * @param FormStateInterface $form_state
   *   The form state.
   *
   * @return array
   *   The modified form array.
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    return $form;
  }

  /**
   * Processes the action link setting form submit.
   *
   * Derived classes will want to override this method.
   *
   * @param array $form
   *   The form array.
   * @param FormStateInterface $form_state
   *   The form state.
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    // Override this.
  }

  /**
   * Validates the action link setting form.
   *
   * Derived classes will want to override this method.
   *
   * @param array $form
   *   The form array.
   * @param FormStateInterface $form_state
   *   The form state.
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    // Override this.
  }

  /**
   * Provides the action link plugin's default configuration.
   *
   * Derived classes will want to override this method.
   *
   * @return array
   *   The plugin configuration array.
   */
  public function defaultConfiguration() {
    return [];
  }

  /**
   * Provides the action link plugin's current configuration array.
   *
   * @return array
   *   An array containing the plugin's current configuration.
   */
  public function getConfiguration() {
    return $this->configuration;
  }

  /**
   * Updates the plugin's current configuration.
   *
   * @param array $configuration
   *   An array containing the plugin's configuration.
   */
  public function setConfiguration(array $configuration) {
    $this->configuration = $configuration;
  }

}
