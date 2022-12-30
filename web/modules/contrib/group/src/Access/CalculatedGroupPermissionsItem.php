<?php

namespace Drupal\group\Access;

/**
 * Represents a single entry for the calculated group permissions.
 *
 * @see \Drupal\group\Access\ChainGroupPermissionCalculator
 */
class CalculatedGroupPermissionsItem implements CalculatedGroupPermissionsItemInterface {

  /**
   * The scope name.
   *
   * @var string
   */
  protected $scope;

  /**
   * The identifier.
   *
   * @var string|int
   */
  protected $identifier;

  /**
   * The permission names.
   *
   * @var string[]
   */
  protected $permissions;

  /**
   * Whether this entry grants admin rights for the given scope.
   *
   * @var bool
   */
  protected $isAdmin;

  /**
   * Constructs a new CalculatedGroupPermissionsItem.
   *
   * @param string $scope
   *   The scope name.
   * @param string|int $identifier
   *   The identifier within the scope.
   * @param string[] $permissions
   *   The permission names.
   * @param bool $is_admin
   *   (optional) Whether the item grants admin privileges.
   */
  public function __construct($scope, $identifier, $permissions, $is_admin = NULL) {
    $this->scope = $scope;
    $this->identifier = $identifier;
    $this->permissions = array_unique($permissions);

    // @todo Rework for group 8.2.x to no longer use the admin permission.
    // @todo Do make flag default to FALSE and pass role's isAdmin flag instead.
    $this->isAdmin = isset($is_admin)
      ? $is_admin
      : in_array('administer group', $permissions, TRUE);
  }

  /**
   * {@inheritdoc}
   */
  public function getScope() {
    return $this->scope;
  }

  /**
   * {@inheritdoc}
   */
  public function getIdentifier() {
    return $this->identifier;
  }

  /**
   * {@inheritdoc}
   */
  public function getPermissions() {
    return $this->permissions;
  }

  /**
   * {@inheritdoc}
   */
  public function isAdmin() {
    return $this->isAdmin;
  }

  /**
   * {@inheritdoc}
   */
  public function hasPermission($permission) {
    return $this->isAdmin() || in_array($permission, $this->permissions, TRUE);
  }

}
