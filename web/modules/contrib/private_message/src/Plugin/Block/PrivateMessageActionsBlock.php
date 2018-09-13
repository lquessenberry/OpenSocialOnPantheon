<?php

namespace Drupal\private_message\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Block\BlockPluginInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Routing\ResettableStackedRouteMatchInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides the private message actions block.
 *
 * This block holds links to perform actions on a private message thread.
 *
 * @Block(
 *   id = "private_message_actions_block",
 *   admin_label = @Translation("Private Message Actions"),
 *   category =  @Translation("Private Message"),
 * )
 */
class PrivateMessageActionsBlock extends BlockBase implements BlockPluginInterface, ContainerFactoryPluginInterface {

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * The current route matcher.
   *
   * @var \Drupal\Core\Routing\ResettableStackedRouteMatchInterface
   */
  protected $currentRouteMatcher;

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
   * @param \Drupal\Core\Routing\ResettableStackedRouteMatchInterface $currentRouteMatcher
   *   The current route matcher.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    AccountProxyInterface $currentUser,
    ResettableStackedRouteMatchInterface $currentRouteMatcher
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->currentUser = $currentUser;
    $this->currentRouteMatcher = $currentRouteMatcher;
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
      $container->get('current_route_match')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    if ($this->currentUser->hasPermission('use private messaging system') && $this->currentRouteMatcher->getRouteName() == 'private_message.private_message_page') {

      $url = Url::fromRoute('private_message.private_message_create');
      $block['links'] = [
        '#type' => 'link',
        '#title' => $this->t('Create Private Message'),
        '#url' => $url,
      ];

      // Add the default classes, as these are not added when the block output
      // is overridden with a template.
      $block['#attributes']['class'][] = 'block';
      $block['#attributes']['class'][] = 'block-private-message';
      $block['#attributes']['class'][] = 'block-private-message-actions-block';

      return $block;
    }
  }

}
