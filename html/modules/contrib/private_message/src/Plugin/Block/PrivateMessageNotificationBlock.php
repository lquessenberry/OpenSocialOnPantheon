<?php

namespace Drupal\private_message\Plugin\Block;

use Drupal\Core\Access\CsrfTokenGenerator;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Block\BlockPluginInterface;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Url;
use Drupal\private_message\Service\PrivateMessageServiceInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides the private message notification block.
 *
 * @Block(
 *   id = "private_message_notification_block",
 *   admin_label = @Translation("Private Message Notification"),
 *   category =  @Translation("Private Message"),
 * )
 */
class PrivateMessageNotificationBlock extends BlockBase implements BlockPluginInterface, ContainerFactoryPluginInterface {

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * The CSRF token generator service.
   *
   * @var \Drupal\Core\Access\CsrfTokenGenerator
   */
  protected $csrfToken;

  /**
   * The private message service.
   *
   * @var \Drupal\private_message\Service\PrivateMessageServiceInterface
   */
  protected $privateMessageService;

  /**
   * Constructs a PrivateMessageForm object.
   *
   * @param array $configuration
   *   The block configuration.
   * @param string $plugin_id
   *   The ID of the plugin.
   * @param mixed $plugin_definition
   *   The plugin definition.
   * @param \Drupal\Core\Session\AccountProxyInterface $currentUser
   *   The current user.
   * @param \Drupal\Core\Access\CsrfTokenGenerator $csrfToken
   *   The CSRF token generator service.
   * @param \Drupal\private_message\Service\PrivateMessageServiceInterface $privateMessageService
   *   The private message service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, AccountProxyInterface $currentUser, CsrfTokenGenerator $csrfToken, PrivateMessageServiceInterface $privateMessageService) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->currentUser = $currentUser;
    $this->csrfToken = $csrfToken;
    $this->privateMessageService = $privateMessageService;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('current_user'),
      $container->get('csrf_token'),
      $container->get('private_message.service')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    if ($this->currentUser->isAuthenticated() && $this->currentUser->hasPermission('use private messaging system')) {
      $block = [
        '#theme' => 'private_message_notification_block',
        '#new_message_count' => $this->privateMessageService->getUnreadThreadCount(),
      ];

      $url = Url::fromRoute('private_message.ajax_callback', ['op' => 'get_new_unread_thread_count']);
      $token = $this->csrfToken->get($url->getInternalPath());
      $url->setOptions(['absolute' => TRUE, 'query' => ['token' => $token]]);
      $block['#attached']['drupalSettings']['privateMessageNotificationBlock']['newMessageCountCallback'] = $url->toString();

      $config = $this->getConfiguration();
      $block['#attached']['drupalSettings']['privateMessageNotificationBlock']['ajaxRefreshRate'] = $config['ajax_refresh_rate'];

      $block['#attached']['library'][] = 'private_message/notification_block';

      // Add the default classes, as these are not added when the block output
      // is overridden with a template.
      $block['#attributes']['class'][] = 'block';
      $block['#attributes']['class'][] = 'block-private-message';
      $block['#attributes']['class'][] = 'block-private-message-notification-block';

      return $block;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags() {
    return Cache::mergeTags(parent::getCacheTags(), ['private_message_notification_block:uid:' . $this->currentUser->id()]);
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts() {
    // Vary caching of this block per user.
    return Cache::mergeContexts(parent::getCacheContexts(), ['user']);
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'ajax_refresh_rate' => 15,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $form = parent::blockForm($form, $form_state);

    $config = $this->getConfiguration();

    $form['ajax_refresh_rate'] = [
      '#type' => 'number',
      '#title' => $this->t('Ajax refresh rate'),
      '#default_value' => $config['ajax_refresh_rate'],
      '#min' => 0,
      '#description' => $this->t('The number of seconds between checks to see if there are any new messages. Setting this to a low number will result in more requests to the server, adding overhead and bandwidth. Setting this number to zero will disable ajax refresh, and the inbox will only updated if/when the page is refreshed.'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    $this->configuration['ajax_refresh_rate'] = $form_state->getValue('ajax_refresh_rate');
  }

}
