<?php

namespace Drupal\social_auth\Plugin\Block;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a Social Auth Block for Login.
 *
 * @Block(
 *   id = "social_auth_login",
 *   admin_label = @Translation("Social Auth Login"),
 * )
 */
class SocialAuthLoginBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * Immutable configuration for social_auth.settings.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  private $socialAuthConfig;

  /**
   * SocialAuthLoginBlock constructor.
   *
   * @param array $configuration
   *   The plugin configuration.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Config\ImmutableConfig $social_auth_config
   *   The Immutable configuration for social_oauth.settings.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, ImmutableConfig $social_auth_config) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->socialAuthConfig = $social_auth_config;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
    $configuration,
    $plugin_id,
    $plugin_definition,
    $container->get('config.factory')->get('social_auth.settings')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    return array(
      '#theme' => 'login_with',
      '#social_networks' => $this->socialAuthConfig->get('auth'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function blockAccess(AccountInterface $account) {
    if ($account->isAnonymous()) {
      return AccessResult::allowed();
    }
    return AccessResult::forbidden();
  }

}
