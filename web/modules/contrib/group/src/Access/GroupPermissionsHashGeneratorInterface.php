<?php

namespace Drupal\group\Access;

use Drupal\Core\Session\AccountInterface;

/**
 * Defines the group permissions hash generator interface.
 *
 * @todo Should return a GroupPermissionsHash value object with cache metadata.
 */
interface GroupPermissionsHashGeneratorInterface {

  /**
   * Generates a hash for an account's complete group permissions.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user account for which to get the permissions hash.
   *
   * @return string
   *   A permissions hash.
   */
  public function generateHash(AccountInterface $account);

  /**
   * Gets the cacheability metadata for the generated hash.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user account for which to get the permissions hash.
   *
   * @return \Drupal\Core\Cache\CacheableMetadata
   *   A cacheable metadata object.
   */
  public function getCacheableMetadata(AccountInterface $account);

}
