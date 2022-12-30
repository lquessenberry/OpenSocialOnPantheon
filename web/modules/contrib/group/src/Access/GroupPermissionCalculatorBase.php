<?php

namespace Drupal\group\Access;

use Drupal\Core\Session\AccountInterface;

/**
 * Base class for group permission calculators.
 */
abstract class GroupPermissionCalculatorBase implements GroupPermissionCalculatorInterface {

  /**
   * {@inheritdoc}
   */
  public function calculateAnonymousPermissions() {
    return new RefinableCalculatedGroupPermissions();
  }

  /**
   * {@inheritdoc}
   */
  public function calculateOutsiderPermissions(AccountInterface $account) {
    return new RefinableCalculatedGroupPermissions();
  }

  /**
   * {@inheritdoc}
   */
  public function calculateMemberPermissions(AccountInterface $account) {
    return new RefinableCalculatedGroupPermissions();
  }

}
