<?php

namespace Drupal\group\Access;

/**
 * Defines the calculated group permissions item interface.
 */
interface CalculatedGroupPermissionsItemInterface {

  /**
   * The scope for group based permissions (e.g.: memberships).
   */
  const SCOPE_GROUP = 'group';

  /**
   * The scope for group type based permissions (e.g.: anonymous or outsider).
   */
  const SCOPE_GROUP_TYPE = 'group_type';

  /**
   * Returns the scope of the calculated permissions item.
   *
   * @return string
   *   The scope name.
   */
  public function getScope();

  /**
   * Returns the identifier within the scope.
   *
   * @return string|int
   *   The identifier.
   */
  public function getIdentifier();

  /**
   * Returns the permissions for the calculated permissions item.
   *
   * @return string[]
   *   The permission names.
   */
  public function getPermissions();

  /**
   * Returns whether this item grants admin privileges in its scope.
   *
   * @return bool
   *   Whether this item grants admin privileges.
   */
  public function isAdmin();

  /**
   * Returns whether this item has a given permission.
   *
   * This should take ::isAdmin() into account.
   *
   * @param string $permission
   *   The permission name.
   *
   * @return bool
   *   Whether this item has the permission.
   */
  public function hasPermission($permission);

}
