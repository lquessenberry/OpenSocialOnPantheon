<?php

namespace Drupal\group\Access;

use Drupal\Core\Session\AccountInterface;
use Drupal\group\Entity\GroupInterface;

/**
 * Defines the group permission checker interface.
 */
interface GroupPermissionCheckerInterface {

  /**
   * Checks whether an account has a permission in a group.
   *
   * @param string $permission
   *   The name of the permission to check for.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user account for which to check the group permissions.
   * @param \Drupal\group\Entity\GroupInterface $group
   *   The group to check the permission against.
   *
   * @return bool
   *   Whether the account has the group permission.
   */
  public function hasPermissionInGroup($permission, AccountInterface $account, GroupInterface $group);

}
