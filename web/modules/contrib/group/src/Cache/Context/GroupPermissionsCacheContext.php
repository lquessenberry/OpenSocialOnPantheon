<?php

namespace Drupal\group\Cache\Context;

use Drupal\Core\Cache\Context\CacheContextInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\group\Access\GroupPermissionsHashGeneratorInterface;

/**
 * Defines a cache context for "per group membership permissions" caching.
 *
 * Please read the following guide on how to best use this context:
 * https://www.drupal.org/docs/8/modules/group/turning-off-caching-when-it-doesnt-make-sense.
 *
 * Cache context ID: 'user.group_permissions'.
 */
class GroupPermissionsCacheContext implements CacheContextInterface {

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * The permissions hash generator.
   *
   * @var \Drupal\group\Access\GroupPermissionsHashGeneratorInterface
   */
  protected $permissionsHashGenerator;

  /**
   * Constructs a new GroupMembershipPermissionsCacheContext class.
   *
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   The current user.
   * @param \Drupal\group\Access\GroupPermissionsHashGeneratorInterface $hash_generator
   *   The permissions hash generator.
   */
  public function __construct(AccountProxyInterface $current_user, GroupPermissionsHashGeneratorInterface $hash_generator) {
    $this->currentUser = $current_user;
    $this->permissionsHashGenerator = $hash_generator;
  }

  /**
   * {@inheritdoc}
   */
  public static function getLabel() {
    return t("Group permissions");
  }

  /**
   * {@inheritdoc}
   */
  public function getContext() {
    // @todo Take bypass permission into account, delete permission in 8.2.x.
    return $this->permissionsHashGenerator->generateHash($this->currentUser);
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheableMetadata() {
    // @todo Take bypass permission into account, delete permission in 8.2.x.
    return $this->permissionsHashGenerator->getCacheableMetadata($this->currentUser);
  }

}
