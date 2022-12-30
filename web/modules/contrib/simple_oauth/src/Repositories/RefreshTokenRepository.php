<?php

namespace Drupal\simple_oauth\Repositories;

use League\OAuth2\Server\Entities\RefreshTokenEntityInterface;

/**
 * The refresh token repository.
 */
class RefreshTokenRepository implements OptionalRefreshTokenRepositoryInterface {

  use RevocableTokenRepositoryTrait;

  /**
   * The bundle ID.
   *
   * @var string
   */
  protected static string $bundleId = 'refresh_token';

  /**
   * The OAuth2 entity class name.
   *
   * @var string
   */
  protected static string $entityClass = 'Drupal\simple_oauth\Entities\RefreshTokenEntity';

  /**
   * The OAuth2 entity interface name.
   *
   * @var string
   */
  protected static string $entityInterface = 'League\OAuth2\Server\Entities\RefreshTokenEntityInterface';

  /**
   * Boolean indicating if the refresh token is enabled.
   *
   * @var bool
   */
  protected bool $refreshTokenEnabled = TRUE;

  /**
   * {@inheritdoc}
   */
  public function getNewRefreshToken() {
    return $this->refreshTokenEnabled ? $this->getNew() : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function persistNewRefreshToken(RefreshTokenEntityInterface $refresh_token_entity) {
    $this->persistNew($refresh_token_entity);
  }

  /**
   * {@inheritdoc}
   */
  public function revokeRefreshToken($token_id) {
    $this->revoke($token_id);
  }

  /**
   * {@inheritdoc}
   */
  public function isRefreshTokenRevoked($token_id) {
    return $this->isRevoked($token_id);
  }

  /**
   * {@inheritdoc}
   */
  public function disableRefreshToken(): void {
    $this->refreshTokenEnabled = FALSE;
  }

}
