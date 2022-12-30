<?php

namespace Drupal\group\Access;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\PrivateKey;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Site\Settings;

/**
 * Generates and caches the permissions hash for a group membership.
 */
class GroupPermissionsHashGenerator implements GroupPermissionsHashGeneratorInterface {

  /**
   * The private key service.
   *
   * @var \Drupal\Core\PrivateKey
   */
  protected $privateKey;

  /**
   * The cache backend interface to use for the static cache.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected $static;

  /**
   * The group permission calculator.
   *
   * @var \Drupal\group\Access\GroupPermissionCalculatorInterface
   */
  protected $groupPermissionCalculator;

  /**
   * Constructs a GroupPermissionsHashGenerator object.
   *
   * @param \Drupal\Core\PrivateKey $private_key
   *   The private key service.
   * @param \Drupal\Core\Cache\CacheBackendInterface $static
   *   The cache backend interface to use for the static cache.
   * @param \Drupal\group\Access\GroupPermissionCalculatorInterface $permission_calculator
   *   The group permission calculator.
   */
  public function __construct(PrivateKey $private_key, CacheBackendInterface $static, GroupPermissionCalculatorInterface $permission_calculator) {
    $this->privateKey = $private_key;
    $this->static = $static;
    $this->groupPermissionCalculator = $permission_calculator;
  }

  /**
   * {@inheritdoc}
   */
  public function generateHash(AccountInterface $account) {
    // We can use a simple per-user static cache here because we already cache
    // the permissions more efficiently in the group permission calculator. On
    // top of that, there is only a tiny chance of a hash being generated for
    // more than one account during a single request.
    $cid = 'group_permissions_hash_' . $account->id();

    // Retrieve the hash from the static cache if available.
    if ($static_cache = $this->static->get($cid)) {
      return $static_cache->data;
    }
    // Otherwise hash the permissions and store them in the static cache.
    else {
      $calculated_permissions = $this->groupPermissionCalculator->calculatePermissions($account);

      $permissions = [];
      foreach ($calculated_permissions->getItems() as $item) {
        // If the calculated permissions item grants admin rights, we can
        // simplify the entry by setting it to 'is-admin' rather than a list of
        // permissions. This will ensure admins for the given scope item always
        // match even if their list of permissions differs.
        if ($item->isAdmin()) {
          $item_permissions = 'is-admin';
        }
        else {
          $item_permissions = $item->getPermissions();

          // Sort the permissions by name to ensure we don't get mismatching
          // hashes for people with the same permissions, just because the order
          // of the permissions happened to differ.
          sort($item_permissions);
        }

        $permissions[$item->getIdentifier()] = $item_permissions;
      }

      // Sort the result by key to ensure we don't get mismatching hashes for
      // people with the same permissions, just because the order of the keys
      // happened to differ.
      ksort($permissions);

      $hash = $this->hash(serialize($permissions));
      $this->static->set($cid, $hash, Cache::PERMANENT, $calculated_permissions->getCacheTags());
      return $hash;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheableMetadata(AccountInterface $account) {
    return CacheableMetadata::createFromObject($this->groupPermissionCalculator->calculatePermissions($account));
  }

  /**
   * Hashes the given string.
   *
   * @param string $identifier
   *   The string to be hashed.
   *
   * @return string
   *   The hash.
   */
  protected function hash($identifier) {
    return hash('sha256', $this->privateKey->get() . Settings::getHashSalt() . $identifier);
  }

}
