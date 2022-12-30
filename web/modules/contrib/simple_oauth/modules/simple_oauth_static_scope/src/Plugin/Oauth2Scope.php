<?php

namespace Drupal\simple_oauth_static_scope\Plugin;

use Drupal\Component\Plugin\PluginBase;
use Drupal\simple_oauth\Oauth2ScopeInterface;

/**
 * Default object used for OAuth2 Scope plugins.
 *
 * @see \Drupal\simple_oauth_static_scope\Plugin\Oauth2ScopeManager
 */
class Oauth2Scope extends PluginBase implements Oauth2ScopePluginInterface {

  /**
   * {@inheritdoc}
   */
  public function id() {
    return $this->getPluginId();
  }

  /**
   * {@inheritdoc}
   */
  public function getName(): string {
    return $this->getPluginId();
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription(): string {
    return $this->pluginDefinition['description'];
  }

  /**
   * {@inheritdoc}
   */
  public function getGrantTypes(): array {
    return $this->pluginDefinition['grant_types'];
  }

  /**
   * {@inheritdoc}
   */
  public function getGrantTypeDescription(string $grant_type): ?string {
    $grant_types = $this->getGrantTypes();
    return $grant_types[$grant_type]['description'] ?? NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function isGrantTypeEnabled(string $grant_type): bool {
    return array_key_exists($grant_type, $this->getGrantTypes());
  }

  /**
   * {@inheritdoc}
   */
  public function isUmbrella(): bool {
    return $this->pluginDefinition['umbrella'];
  }

  /**
   * {@inheritdoc}
   */
  public function getParent(): ?string {
    $parent = $this->pluginDefinition['parent'] ?? NULL;
    return !$this->isUmbrella() ? $parent : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getGranularity(): ?string {
    return !$this->isUmbrella() ? $this->pluginDefinition['granularity'] : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getPermission(): ?string {
    return !$this->isUmbrella() && $this->getGranularity() === Oauth2ScopeInterface::GRANULARITY_PERMISSION ? $this->pluginDefinition['permission'] : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getRole(): ?string {
    return !$this->isUmbrella() && $this->getGranularity() === Oauth2ScopeInterface::GRANULARITY_ROLE ? $this->pluginDefinition['role'] : NULL;
  }

}
