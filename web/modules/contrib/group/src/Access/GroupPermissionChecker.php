<?php

namespace Drupal\group\Access;

use Drupal\Core\Session\AccountInterface;
use Drupal\group\Entity\GroupInterface;

/**
 * Calculates group permissions for an account.
 */
class GroupPermissionChecker implements GroupPermissionCheckerInterface {

  /**
   * The group permission calculator.
   *
   * @var \Drupal\group\Access\ChainGroupPermissionCalculatorInterface
   */
  protected $groupPermissionCalculator;

  /**
   * Constructs a GroupPermissionChecker object.
   *
   * @param \Drupal\group\Access\ChainGroupPermissionCalculatorInterface $permission_calculator
   *   The group permission calculator.
   */
  public function __construct(ChainGroupPermissionCalculatorInterface $permission_calculator) {
    $this->groupPermissionCalculator = $permission_calculator;
  }

  /**
   * {@inheritdoc}
   */
  public function hasPermissionInGroup($permission, AccountInterface $account, GroupInterface $group) {
    // If the account can bypass all group access, return immediately.
    if ($account->hasPermission('bypass group access')) {
      return TRUE;
    }

    $calculated_permissions = $this->groupPermissionCalculator->calculatePermissions($account);

    // If the user has member permissions for this group, check those, otherwise
    // we need to check the group type permissions instead, i.e.: the ones for
    // anonymous or outsider audiences.
    $item = $calculated_permissions->getItem(CalculatedGroupPermissionsItemInterface::SCOPE_GROUP, $group->id());
    if ($item === FALSE) {
      $item = $calculated_permissions->getItem(CalculatedGroupPermissionsItemInterface::SCOPE_GROUP_TYPE, $group->bundle());
    }

    return $item->hasPermission($permission);
  }

}
