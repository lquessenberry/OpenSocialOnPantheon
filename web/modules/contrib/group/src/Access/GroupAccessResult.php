<?php

namespace Drupal\group\Access;

use Drupal\group\Entity\GroupInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Session\AccountInterface;

/**
 * Extends the AccessResult class with group permission checks.
 */
abstract class GroupAccessResult extends AccessResult {

  /**
   * Allows access if the permission is present, neutral otherwise.
   *
   * @todo Keep an eye on the following with regard to using the current user:
   * - https://www.drupal.org/node/2628870
   * - https://www.drupal.org/node/2667018
   *
   * @param \Drupal\group\Entity\GroupInterface $group
   *   The group for which to check a permission.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The account for which to check a permission.
   * @param string $permission
   *   The permission to check for.
   *
   * @return \Drupal\Core\Access\AccessResult
   *   If the account has the permission, isAllowed() will be TRUE, otherwise
   *   isNeutral() will be TRUE.
   */
  public static function allowedIfHasGroupPermission(GroupInterface $group, AccountInterface $account, $permission) {
    return static::allowedIf($group->hasPermission($permission, $account))->addCacheContexts(['user.group_permissions']);
  }

  /**
   * Allows access if the permissions are present, neutral otherwise.
   *
   * @todo Keep an eye on the following with regard to using the current user:
   * - https://www.drupal.org/node/2628870
   * - https://www.drupal.org/node/2667018
   *
   * @param \Drupal\group\Entity\GroupInterface $group
   *   The group for which to check permissions.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The account for which to check permissions.
   * @param array $permissions
   *   The permissions to check.
   * @param string $conjunction
   *   (optional) 'AND' if all permissions are required, 'OR' in case just one.
   *   Defaults to 'AND'.
   *
   * @return \Drupal\Core\Access\AccessResult
   *   If the account has the permissions, isAllowed() will be TRUE, otherwise
   *   isNeutral() will be TRUE.
   */
  public static function allowedIfHasGroupPermissions(GroupInterface $group, AccountInterface $account, array $permissions, $conjunction = 'AND') {
    $access = FALSE;

    if ($conjunction == 'AND' && !empty($permissions)) {
      $access = TRUE;
      foreach ($permissions as $permission) {
        if (!$group->hasPermission($permission, $account)) {
          $access = FALSE;
          break;
        }
      }
    }
    else {
      foreach ($permissions as $permission) {
        if ($group->hasPermission($permission, $account)) {
          $access = TRUE;
          break;
        }
      }
    }

    return static::allowedIf($access)->addCacheContexts(['user.group_permissions']);
  }

}
