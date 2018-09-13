<?php

namespace Drupal\private_message\Plugin\Block;

use Drupal\Core\Access\CsrfTokenGenerator;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Block\BlockPluginInterface;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\EntityManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Url;
use Drupal\private_message\Service\PrivateMessageServiceInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides the private message inbox block.
 *
 * @Block(
 *   id = "private_message_inbox_block",
 *   admin_label = @Translation("Private Message Inbox"),
 *   category =  @Translation("Private Message"),
 * )
 */
class PrivateMessageInboxBlock extends BlockBase implements BlockPluginInterface, ContainerFactoryPluginInterface {

  /**
   * The private message service.
   *
   * @var \Drupal\private_message\Service\PrivateMessageServiceInterface
   */
  protected $privateMessageService;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * The entity manager service.
   *
   * @var \Drupal\Core\Entity\EntityManagerInterface
   */
  protected $entityManager;

  /**
   * The CSRF token generator service.
   *
   * @var \Drupal\Core\Access\CsrfTokenGenerator
   */
  protected $csrfToken;

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
   * @param \Drupal\private_message\Service\PrivateMessageServiceInterface $privateMessageService
   *   The private message service.
   * @param \Drupal\Core\Entity\EntityManagerInterface $entityManager
   *   The entity manager service.
   * @param \Drupal\Core\Access\CsrfTokenGenerator $csrfToken
   *   The CSRF token generator service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, AccountProxyInterface $currentUser, PrivateMessageServiceInterface $privateMessageService, EntityManagerInterface $entityManager, CsrfTokenGenerator $csrfToken) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->currentUser = $currentUser;
    $this->privateMessageService = $privateMessageService;
    $this->entityManager = $entityManager;
    $this->csrfToken = $csrfToken;
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
      $container->get('private_message.service'),
      $container->get('entity.manager'),
      $container->get('csrf_token')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    if ($this->currentUser->isAuthenticated() && $this->currentUser->hasPermission('use private messaging system')) {
      $config = $this->getConfiguration();
      $thread_info = $this->privateMessageService->getThreadsForUser($config['thread_count']);
      if (count($thread_info['threads'])) {
        $view_builder = $this->entityManager->getViewBuilder('private_message_thread');
        $threads = $thread_info['threads'];

        foreach ($threads as $thread) {
          $block[$thread->id()] = $view_builder->view($thread, 'inbox');
        }

        $block['#attached']['library'][] = 'private_message/inbox_block';
        if (count($threads) && $thread_info['next_exists']) {
          $prev_url = Url::fromRoute('private_message.ajax_callback', ['op' => 'get_old_inbox_threads']);
          $prev_token = $this->csrfToken->get($prev_url->getInternalPath());
          $prev_url->setOptions(['absolute' => TRUE, 'query' => ['token' => $prev_token]]);

          $new_url = Url::fromRoute('private_message.ajax_callback', ['op' => 'get_new_inbox_threads']);
          $new_token = $this->csrfToken->get($new_url->getInternalPath());
          $new_url->setOptions(['absolute' => TRUE, 'query' => ['token' => $new_token]]);

          $last_thread = array_pop($threads);
          $block['#attached']['drupalSettings']['privateMessageInboxBlock'] = [
            'oldestTimestamp' => $last_thread->get('updated')->value,
            'loadPrevUrl' => $prev_url->toString(),
            'loadNewUrl' => $new_url->toString(),
            'threadCount' => $config['ajax_load_count'],
          ];
        }
        else {
          $block['#attached']['drupalSettings']['privateMessageInboxBlock'] = [
            'oldestTimestamp' => FALSE,
          ];
        }
      }
      else {
        $block['no_threads'] = [
          '#prefix' => '<p>',
          '#suffix' => '</p>',
          '#markup' => $this->t('You do not have any private messages'),
        ];
      }

      $new_url = Url::fromRoute('private_message.ajax_callback', ['op' => 'get_new_inbox_threads']);
      $new_token = $this->csrfToken->get($new_url->getInternalPath());
      $new_url->setOptions(['absolute' => TRUE, 'query' => ['token' => $new_token]]);

      $block['#attached']['drupalSettings']['privateMessageInboxBlock']['loadNewUrl'] = $new_url->toString();

      $config = $this->getConfiguration();
      $block['#attached']['drupalSettings']['privateMessageInboxBlock']['ajaxRefreshRate'] = $config['ajax_refresh_rate'];

      // Add the default classes, as these are not added when the block output
      // is overridden with a template.
      $block['#attributes']['class'][] = 'block';
      $block['#attributes']['class'][] = 'block-private-message';
      $block['#attributes']['class'][] = 'block-private-message-inbox-block';

      return $block;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags() {
    return Cache::mergeTags(parent::getCacheTags(), ['private_message_inbox_block:uid:' . $this->currentUser->id()]);
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
      'thread_count' => 5,
      'ajax_load_count' => 5,
      'ajax_refresh_rate' => 15,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $form = parent::blockForm($form, $form_state);

    $config = $this->getConfiguration();

    $form['thread_count'] = [
      '#type' => 'number',
      '#title' => $this->t('Number of threads to show'),
      '#description' => $this->t('The number of threads to be shown in the block'),
      '#default_value' => $config['thread_count'],
      '#min' => 1,
    ];

    $form['ajax_load_count'] = [
      '#type' => 'number',
      '#title' => $this->t('Number of threads to load with ajax'),
      '#description' => $this->t('The number of threads to be loaded when the load previous link is clicked'),
      '#default_value' => $config['ajax_load_count'],
      '#min' => 1,
    ];

    $form['ajax_refresh_rate'] = [
      '#type' => 'number',
      '#title' => $this->t('Ajax refresh rate'),
      '#default_value' => $config['ajax_refresh_rate'],
      '#min' => 0,
      '#description' => $this->t('The number of seconds after which the inbox should refresh itself. Setting this to a low number will result in more requests to the server, adding overhead and bandwidth. Setting this number to zero will disable ajax refresh, and the inbox will only updated if/when the page is refreshed.'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    $this->configuration['thread_count'] = $form_state->getValue('thread_count');
    $this->configuration['ajax_load_count'] = $form_state->getValue('ajax_load_count');
    $this->configuration['ajax_refresh_rate'] = $form_state->getValue('ajax_refresh_rate');
  }

}
