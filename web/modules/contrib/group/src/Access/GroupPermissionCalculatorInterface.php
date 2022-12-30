<?php

namespace Drupal\group\Access;

use Drupal\Core\Session\AccountInterface;

/**
 * Defines the group permission calculator interface.
 *
 * Please make sure that when calculating permissions, you attach the right
 * cacheable metadata. This includes cache contexts if your implementation
 * causes the calculated permissions to vary by something. Any cache contexts
 * defined in the getPersistent...CacheContexts() methods will automatically be
 * added to the corresponding calculated permissions.
 *
 * It's of the utmost importance that you properly declare any cache context
 * that should always be present in the _CACHE_CONTEXTS constants. For instance:
 * If your outsider permissions are the same for everyone but user 1337, then
 * your outsider permissions must ALL vary by the user cache context.
 *
 * Do NOT use the user.group_permissions in any of the calculations as that
 * cache context is essentially a wrapper around the calculated permissions and
 * you'd therefore end up in an infinite loop.
 */
interface GroupPermissionCalculatorInterface {

  /**
   * The cache contexts that should always be present on anonymous permissions.
   *
   * Override this in your implementation if you need to set any cache contexts.
   */
  const ANONYMOUS_CACHE_CONTEXTS = [];

  /**
   * The cache contexts that should always be present on outsider permissions.
   *
   * Override this in your implementation if you need to set any cache contexts.
   */
  const OUTSIDER_CACHE_CONTEXTS = [];

  /**
   * The cache contexts that should always be present on member permissions.
   *
   * Override this in your implementation if you need to set any cache contexts.
   */
  const MEMBER_CACHE_CONTEXTS = [];

  /**
   * Calculates the anonymous group permissions.
   *
   * @return \Drupal\group\Access\CalculatedGroupPermissionsInterface
   *   An object representing the anonymous group permissions.
   */
  public function calculateAnonymousPermissions();

  /**
   * Calculates the outsider group permissions for an account.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user account for which to calculate the outsider permissions.
   *
   * @return \Drupal\group\Access\CalculatedGroupPermissionsInterface
   *   An object representing the outsider group permissions.
   */
  public function calculateOutsiderPermissions(AccountInterface $account);

  /**
   * Calculates the member group permissions for an account.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user account for which to calculate the member permissions.
   *
   * @return \Drupal\group\Access\CalculatedGroupPermissionsInterface
   *   An object representing the member group permissions.
   */
  public function calculateMemberPermissions(AccountInterface $account);

}
