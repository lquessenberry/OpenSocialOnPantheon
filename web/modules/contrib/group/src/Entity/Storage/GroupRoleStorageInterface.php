<?php

namespace Drupal\group\Entity\Storage;

use Drupal\Core\Config\Entity\ConfigEntityStorageInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\group\Entity\GroupInterface;
use Drupal\User\RoleInterface;

/**
 * Defines an interface for group role entity storage classes.
 */
interface GroupRoleStorageInterface extends ConfigEntityStorageInterface {

  /**
   * Retrieves all GroupRole entities for a user within a group.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The account to load the group role entities for.
   * @param \Drupal\group\Entity\GroupInterface $group
   *   The group entity to find the user's role entities in.
   * @param boolean $include_implied
   *   (optional) Whether to include the implied roles 'anonymous', 'outsider'
   *   and 'member'. Defaults to TRUE.
   *
   * @return \Drupal\group\Entity\GroupRoleInterface[]
   *   The group roles matching the criteria.
   */
  public function loadByUserAndGroup(AccountInterface $account, GroupInterface $group, $include_implied = TRUE);

  /**
   * Retrieves all synchronized GroupRole entities by group types.
   *
   * @param string[] $group_type_ids
   *   The list of group type IDs to load the synchronized group roles for.
   *
   * @return \Drupal\group\Entity\GroupRoleInterface[]
   *   The group roles matching the group types.
   */
  public function loadSynchronizedByGroupTypes(array $group_type_ids);

  /**
   * Retrieves all synchronized GroupRole entities by user roles.
   *
   * @param string[] $role_ids
   *   The list of user role IDs to load the synchronized group roles for.
   *
   * @return \Drupal\group\Entity\GroupRoleInterface[]
   *   The group roles matching the user roles.
   */
  public function loadSynchronizedByUserRoles(array $role_ids);

  /**
   * Creates internal group roles for group types.
   *
   * Internal group roles are the bare minimum group roles that a group type
   * needs to function properly, such as the Member group role.
   *
   * @param string[] $group_type_ids
   *   (optional) A list of group type IDs to synchronize roles for. Leave empty
   *   to synchronize roles for all group types.
   */
  public function createInternal($group_type_ids = NULL);

  /**
   * Creates group roles for all user roles.
   *
   * @param string[] $group_type_ids
   *   (optional) A list of group type IDs to synchronize roles for. Leave empty
   *   to synchronize roles for all group types.
   * @param string[] $role_ids
   *   (optional) A list of user role IDs to synchronize. Leave empty to
   *   synchronize all user roles.
   */
  public function createSynchronized($group_type_ids = NULL, $role_ids = NULL);

  /**
   * Updates the label of all group roles for a user role.
   *
   * @param \Drupal\User\RoleInterface $role
   *   The user role to update the group role labels for.
   */
  public function updateSynchronizedLabels(RoleInterface $role);

  /**
   * Resets the internal, static cache used by ::loadByUserAndGroup().
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The account to reset the cached group roles for.
   * @param \Drupal\group\Entity\GroupInterface $group
   *   (optional) The group to reset the user's cached group roles for. Leave
   *   blank to reset the user's roles in all groups.
   */
  public function resetUserGroupRoleCache(AccountInterface $account, GroupInterface $group = NULL);

}
