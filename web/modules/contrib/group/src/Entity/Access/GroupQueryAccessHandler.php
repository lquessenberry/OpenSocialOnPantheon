<?php

namespace Drupal\group\Entity\Access;

use Drupal\Core\Session\AccountInterface;
use Drupal\entity\QueryAccess\ConditionGroup;
use Drupal\group\Access\CalculatedGroupPermissionsItemInterface as CGPII;

/**
 * Controls query access for group entities.
 *
 * @see \Drupal\entity\QueryAccess\QueryAccessHandler
 */
class GroupQueryAccessHandler extends QueryAccessHandlerBase {

  /**
   * Retrieves the group permission name for the given operation.
   *
   * @param string $operation
   *   The access operation. Usually one of "view", "update" or "delete".
   *
   * @return string
   *   The group permission name.
   */
  protected function getPermissionName($operation) {
    switch ($operation) {
      // @todo Could use the below if permission were named 'update group'.
      case 'update':
        $permission = 'edit group';
        break;

      case 'delete':
      case 'view':
        $permission = "$operation group";
        break;

      default:
        $permission = 'view group';
    }

    return $permission;
  }

  /**
   * Builds the conditions for the given operation and account.
   *
   * @param string $operation
   *   The access operation. Usually one of "view", "update" or "delete".
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user for which to restrict access.
   *
   * Please note: Just like the AccessResult::cachePerPermissions problem
   * outlined in https://www.drupal.org/project/drupal/issues/2628870, we also
   * add current user dependent cache contexts here even though the passed in
   * account might be different. This is a problem for core to fix but I wanted
   * to give the heads up regardless.
   *
   * @return \Drupal\entity\QueryAccess\ConditionGroup
   *   The conditions.
   */
  protected function buildConditions($operation, AccountInterface $account) {
    $conditions = new ConditionGroup('OR');

    // @todo Remove these lines once we kill the bypass permission.
    // If the account can bypass group access, we do not alter the query at all.
    $conditions->addCacheContexts(['user.permissions']);
    if ($account->hasPermission('bypass group access')) {
      return $conditions;
    }

    $permission = $this->getPermissionName($operation);
    $conditions->addCacheContexts(['user.group_permissions']);

    $calculated_permissions = $this->groupPermissionCalculator->calculatePermissions($account);
    $allowed_ids = $allowed_any_by_status_ids = $allowed_own_by_status_ids = $member_group_ids = [];
    $check_published = $operation === 'view';
    foreach ($calculated_permissions->getItems() as $item) {
      $identifier = $item->getIdentifier();
      $scope = $item->getScope();

      // Gather all of the groups the user is a member of.
      if ($scope === CGPII::SCOPE_GROUP) {
        $member_group_ids[] = $identifier;
      }

      if ($item->hasPermission('administer group')) {
        $allowed_ids[$scope][] = $identifier;
      }
      elseif (!$check_published) {
        if ($item->hasPermission($permission)) {
          $allowed_ids[$scope][] = $identifier;
        }
      }
      else {
        if ($item->hasPermission($permission)) {
          $allowed_any_by_status_ids[$scope][1][] = $identifier;
        }
        if ($item->hasPermission('view any unpublished group')) {
          $allowed_any_by_status_ids[$scope][0][] = $identifier;
        }
        if ($item->hasPermission('view own unpublished group')) {
          $allowed_own_by_status_ids[$scope][0][] = $identifier;
        }
      }
    }

    // If no group type or group gave access, we deny access altogether.
    if (empty($allowed_ids) && empty($allowed_any_by_status_ids) && empty($allowed_own_by_status_ids)) {
      $conditions->alwaysFalse();
      return $conditions;
    }

    // We might see multiple values in the $member_group_ids variable because we
    // looped over all calculated permissions multiple times.
    if (!empty($member_group_ids)) {
      $member_group_ids = array_unique($member_group_ids);
    }

    // Add the allowed group types to the query (if any).
    if (!empty($allowed_ids[CGPII::SCOPE_GROUP_TYPE])) {
      $status_conditions = new ConditionGroup();
      $status_conditions->addCondition('type', array_unique($allowed_ids[CGPII::SCOPE_GROUP_TYPE]));

      // If the user had memberships, we need to make sure they are excluded
      // from group type based matches as the memberships' permissions take
      // precedence.
      if (!empty($member_group_ids)) {
        $status_conditions->addCondition('id', $member_group_ids, 'NOT IN');
      }

      $conditions->addCondition($status_conditions);
    }

    // Add the memberships with access to the query (if any).
    if (!empty($allowed_ids[CGPII::SCOPE_GROUP])) {
      $conditions->addCondition('id', array_unique($allowed_ids[CGPII::SCOPE_GROUP]));
    }

    if ($check_published) {
      foreach ([0, 1] as $status) {
        // Nothing gave (un)published access so bail out entirely.
        if (empty($allowed_any_by_status_ids[CGPII::SCOPE_GROUP_TYPE][$status])
          && empty($allowed_any_by_status_ids[CGPII::SCOPE_GROUP][$status])
          && empty($allowed_own_by_status_ids[CGPII::SCOPE_GROUP_TYPE][$status])
          && empty($allowed_own_by_status_ids[CGPII::SCOPE_GROUP][$status])
        ) {
          continue;
        }

        $status_conditions = new ConditionGroup();
        $status_conditions->addCondition('status', $status);
        $status_conditions_inner = new ConditionGroup('OR');

        // Add the allowed group types to the query (if any).
        if (!empty($allowed_any_by_status_ids[CGPII::SCOPE_GROUP_TYPE][$status])) {
          $sub_condition = new ConditionGroup();
          $sub_condition->addCondition('type', array_unique($allowed_any_by_status_ids[CGPII::SCOPE_GROUP_TYPE][$status]), 'IN');

          // If the user had memberships, we need to make sure they are excluded
          // from group type based matches as the memberships' permissions take
          // precedence.
          if (!empty($member_group_ids)) {
            $sub_condition->addCondition('id', $member_group_ids, 'NOT IN');
          }

          $status_conditions_inner->addCondition($sub_condition);
        }

        // Add the memberships with access to the query (if any).
        if (!empty($allowed_any_by_status_ids[CGPII::SCOPE_GROUP][$status])) {
          $status_conditions_inner->addCondition('id', array_unique($allowed_any_by_status_ids[CGPII::SCOPE_GROUP][$status]), 'IN');
        }

        // Nothing gave owner access so try the next publication status.
        if (empty($allowed_own_by_status_ids[CGPII::SCOPE_GROUP_TYPE][$status]) && empty($allowed_own_by_status_ids[CGPII::SCOPE_GROUP][$status])) {
          $status_conditions->addCondition($status_conditions_inner);
          $conditions->addCondition($status_conditions);
          continue;
        }
        $conditions->addCacheContexts(['user']);

        $status_owner_conditions = new ConditionGroup();
        $status_owner_conditions->addCondition('uid', $account->id());
        $status_owner_conditions_inner = new ConditionGroup('OR');

        // Add the allowed owner group types to the query (if any).
        if (!empty($allowed_own_by_status_ids[CGPII::SCOPE_GROUP_TYPE][$status])) {
          $sub_condition = new ConditionGroup();
          $sub_condition->addCondition('type', array_unique($allowed_own_by_status_ids[CGPII::SCOPE_GROUP_TYPE][$status]), 'IN');

          // If the user had memberships, we need to make sure they are excluded
          // from group type based matches as the memberships' permissions take
          // precedence.
          if (!empty($member_group_ids)) {
            $sub_condition->addCondition('id', $member_group_ids, 'NOT IN');
          }

          $status_owner_conditions_inner->addCondition($sub_condition);
        }

        // Add the owner memberships with access to the query (if any).
        if (!empty($allowed_own_by_status_ids[CGPII::SCOPE_GROUP][$status])) {
          $status_owner_conditions_inner->addCondition('id', array_unique($allowed_own_by_status_ids[CGPII::SCOPE_GROUP][$status]) , 'IN');
        }

        $status_owner_conditions->addCondition($status_owner_conditions_inner);
        $status_conditions_inner->addCondition($status_owner_conditions);
        $status_conditions->addCondition($status_conditions_inner);
        $conditions->addCondition($status_conditions);
      }
    }

    return $conditions;
  }

}
