<?php

namespace Drupal\group\Access;

use Drupal\Core\Session\AccountInterface;

/**
 * Runs the added calculators one by one until the full permissions are built.
 *
 * Each calculator in the chain can be another chain, which is why this
 * interface extends the permission calculator one.
 */
interface ChainGroupPermissionCalculatorInterface extends GroupPermissionCalculatorInterface {

  /**
   * Adds a calculator.
   *
   * @param \Drupal\group\Access\GroupPermissionCalculatorInterface $calculator
   *   The calculator.
   *
   * @return mixed
   */
  public function addCalculator(GroupPermissionCalculatorInterface $calculator);

  /**
   * Gets all added calculators.
   *
   * @return \Drupal\group\Access\GroupPermissionCalculatorInterface[]
   *   The calculators.
   */
  public function getCalculators();

  /**
   * Calculates the full group permissions for an authenticated account.
   *
   * This includes both outsider and member permissions.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user account for which to retrieve the permissions.
   *
   * @return \Drupal\group\Access\CalculatedGroupPermissionsInterface
   *   An object representing the full authenticated group permissions.
   */
  public function calculateAuthenticatedPermissions(AccountInterface $account);

  /**
   * Calculates the full group permissions for an account.
   *
   * This could either include anonymous permissions or both outsider and member
   * permissions, depending on the account's anonymous status.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user account for which to retrieve the permissions.
   *
   * @return \Drupal\group\Access\CalculatedGroupPermissionsInterface
   *   An object representing the full group permissions.
   */
  public function calculatePermissions(AccountInterface $account);

}
