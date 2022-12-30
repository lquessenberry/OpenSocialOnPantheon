<?php

namespace Drupal\simple_oauth\Repositories;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\simple_oauth\Oauth2ScopeInterface;
use Drupal\simple_oauth\Oauth2ScopeProviderInterface;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Exception\OAuthServerException;
use League\OAuth2\Server\Repositories\ScopeRepositoryInterface;
use Drupal\simple_oauth\Entities\ScopeEntity;

/**
 * The repository for scopes.
 */
class ScopeRepository implements ScopeRepositoryInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The scope provider.
   *
   * @var \Drupal\simple_oauth\Oauth2ScopeProviderInterface
   */
  protected Oauth2ScopeProviderInterface $scopeProvider;

  /**
   * ScopeRepository constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\simple_oauth\Oauth2ScopeProviderInterface $scope_provider
   *   The scope provider.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, Oauth2ScopeProviderInterface $scope_provider) {
    $this->entityTypeManager = $entity_type_manager;
    $this->scopeProvider = $scope_provider;
  }

  /**
   * {@inheritdoc}
   */
  public function getScopeEntityByIdentifier($identifier) {
    $scope = $this->scopeProvider->loadByName($identifier);
    return $scope ? $this->scopeFactory($scope) : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function finalizeScopes(array $scopes, $grant_type, ClientEntityInterface $client_entity, $user_identifier = NULL) {
    $default_user = NULL;
    if (!$client_entity->getDrupalEntity()->get('user_id')->isEmpty()) {
      $default_user = $client_entity->getDrupalEntity()->get('user_id')->entity;
    }
    /** @var \Drupal\user\UserInterface $user */
    $user = $user_identifier
      ? $this->entityTypeManager->getStorage('user')->load($user_identifier)
      : $default_user;
    if (!$user) {
      return [];
    }

    $default_scopes = [];
    $client_drupal_entity = $client_entity->getDrupalEntity();
    if (!$client_drupal_entity->get('scopes')->isEmpty()) {
      $default_scopes = array_map(function (Oauth2ScopeInterface $scope) {
        return $this->scopeFactory($scope);
      }, $client_drupal_entity->get('scopes')->getScopes());
    }

    $finalized_scopes = !empty($scopes) ? $scopes : $default_scopes;

    // Validate scopes if the associated grant type is enabled.
    foreach ($finalized_scopes as $finalized_scope) {
      if ($finalized_scope instanceof ScopeEntity && !$finalized_scope->getScopeObject()->isGrantTypeEnabled($grant_type)) {
        throw OAuthServerException::invalidScope($finalized_scope->getIdentifier());
      }
    }

    return $finalized_scopes;
  }

  /**
   * Build a scope entity.
   *
   * @param \Drupal\simple_oauth\Oauth2ScopeInterface $scope
   *   The associated scope.
   *
   * @return \League\OAuth2\Server\Entities\ScopeEntityInterface
   *   The initialized scope entity.
   */
  protected function scopeFactory(Oauth2ScopeInterface $scope) {
    return new ScopeEntity($scope);
  }

}
