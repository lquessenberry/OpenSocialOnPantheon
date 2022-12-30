<?php

namespace Drupal\group\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\Entity\EntityStorageInterface;

/**
 * Defines the Group role configuration entity.
 *
 * @ConfigEntityType(
 *   id = "group_role",
 *   label = @Translation("Group role"),
 *   label_singular = @Translation("group role"),
 *   label_plural = @Translation("group roles"),
 *   label_count = @PluralTranslation(
 *     singular = "@count group role",
 *     plural = "@count group roles"
 *   ),
 *   handlers = {
 *     "storage" = "Drupal\group\Entity\Storage\GroupRoleStorage",
 *     "access" = "Drupal\group\Entity\Access\GroupRoleAccessControlHandler",
 *     "form" = {
 *       "add" = "Drupal\group\Entity\Form\GroupRoleForm",
 *       "edit" = "Drupal\group\Entity\Form\GroupRoleForm",
 *       "delete" = "Drupal\group\Entity\Form\GroupRoleDeleteForm"
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\group\Entity\Routing\GroupRoleRouteProvider",
 *     },
 *     "list_builder" = "Drupal\group\Entity\Controller\GroupRoleListBuilder",
 *   },
 *   admin_permission = "administer group",
 *   config_prefix = "role",
 *   static_cache = TRUE,
 *   entity_keys = {
 *     "id" = "id",
 *     "weight" = "weight",
 *     "label" = "label"
 *   },
 *   links = {
 *     "add-form" = "/admin/group/types/manage/{group_type}/roles/add",
 *     "collection" = "/admin/group/types/manage/{group_type}/roles",
 *     "delete-form" = "/admin/group/types/manage/{group_type}/roles/{group_role}/delete",
 *     "edit-form" = "/admin/group/types/manage/{group_type}/roles/{group_role}",
 *     "permissions-form" = "/admin/group/types/manage/{group_type}/roles/{group_role}/permissions"
 *   },
 *   config_export = {
 *     "id",
 *     "label",
 *     "weight",
 *     "internal",
 *     "audience",
 *     "group_type",
 *     "permissions_ui",
 *     "permissions"
 *   }
 * )
 */
class GroupRole extends ConfigEntityBase implements GroupRoleInterface {

  /**
   * The machine name of the group role.
   *
   * @var string
   */
  protected $id;

  /**
   * The human-readable name of the group role.
   *
   * @var string
   */
  protected $label;

  /**
   * The weight of the group role in administrative listings.
   *
   * @var int
   */
  protected $weight;

  /**
   * Whether the group role is used internally.
   *
   * Internal roles cannot be edited or assigned directly. They do not show in
   * the list of group roles to edit or assign and do not have an individual
   * permissions page either. Examples of these are the special group roles
   * 'anonymous', 'outsider' and 'member'.
   *
   * @var bool
   */
  protected $internal = FALSE;

  /**
   * The audience the role is intended for.
   *
   * Supported values are: 'anonymous', 'outsider' or 'member'.
   *
   * @var string
   */
  protected $audience = 'member';

  /**
   * The ID of the group type this role belongs to.
   *
   * @var string
   */
  protected $group_type;

  /**
   * Whether the role shows in the default permissions UI.
   *
   * By default, group roles show on the permissions page regardless of their
   * 'internal' property. If you want to hide a group role from that UI, you can
   * do so by setting this to FALSE.
   *
   * @var bool
   */
  protected $permissions_ui = TRUE;

  /**
   * The permissions belonging to the group role.
   *
   * @var string[]
   */
  protected $permissions = [];

  /**
   * {@inheritdoc}
   */
  public function id() {
    return $this->id;
  }

  /**
   * {@inheritdoc}
   */
  public function getWeight() {
    return $this->get('weight');
  }

  /**
   * {@inheritdoc}
   */
  public function setWeight($weight) {
    $this->set('weight', $weight);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function isInternal() {
    return $this->internal;
  }

  /**
   * {@inheritdoc}
   */
  public function isAnonymous() {
    return $this->audience == 'anonymous';
  }

  /**
   * {@inheritdoc}
   */
  public function isOutsider() {
    return $this->audience == 'outsider';
  }

  /**
   * {@inheritdoc}
   */
  public function isMember() {
    // Instead of checking whether the audience property is set to 'member', we
    // check whether it isn't 'anonymous' or 'outsider'. Any unsupported value
    // will therefore default to 'member'.
    return !$this->isAnonymous() && !$this->isOutsider();
  }

  /**
   * {@inheritdoc}
   */
  public function getGroupType() {
    return GroupType::load($this->group_type);
  }

  /**
   * {@inheritdoc}
   */
  public function getGroupTypeId() {
    return $this->group_type;
  }

  /**
   * {@inheritdoc}
   */
  public function inPermissionsUI() {
    return $this->permissions_ui;
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
  public function hasPermission($permission) {
    return in_array($permission, $this->permissions);
  }

  /**
   * {@inheritdoc}
   */
  public function grantPermission($permission) {
    return $this->grantPermissions([$permission]);
  }

  /**
   * {@inheritdoc}
   */
  public function grantPermissions($permissions) {
    $this->permissions = array_unique(array_merge($this->permissions, $permissions));
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function grantAllPermissions() {
    $permissions = $this->getPermissionHandler()->getPermissionsByGroupType($this->getGroupType());

    foreach ($permissions as $permission => $info) {
      if (!in_array($this->audience, $info['allowed for'])) {
        unset($permissions[$permission]);
      }
    }

    return $this->grantPermissions(array_keys($permissions));
  }

  /**
   * {@inheritdoc}
   */
  public function revokePermission($permission) {
    return $this->revokePermissions([$permission]);
  }

  /**
   * {@inheritdoc}
   */
  public function revokePermissions($permissions) {
    $this->permissions = array_diff($this->permissions, $permissions);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function changePermissions(array $permissions = []) {
    // Grant new permissions to the role.
    $grant = array_filter($permissions);
    if (!empty($grant)) {
      $this->grantPermissions(array_keys($grant));
    }

    // Revoke permissions from the role.
    $revoke = array_diff_assoc($permissions, $grant);
    if (!empty($revoke)) {
      $this->revokePermissions(array_keys($revoke));
    }

    return $this;
  }

  /**
   * Returns the group permission handler.
   *
   * @return \Drupal\group\Access\GroupPermissionHandler
   *   The group permission handler.
   */
  protected function getPermissionHandler() {
    return \Drupal::service('group.permissions');
  }

  /**
   * {@inheritdoc}
   */
  protected function urlRouteParameters($rel) {
    $uri_route_parameters = parent::urlRouteParameters($rel);
    $uri_route_parameters['group_type'] = $this->getGroupTypeId();
    return $uri_route_parameters;
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies() {
    parent::calculateDependencies();
    $this->addDependency('config', $this->getGroupType()->getConfigDependencyName());
  }

  /**
   * {@inheritdoc}
   */
  public static function postLoad(EntityStorageInterface $storage, array &$entities) {
    parent::postLoad($storage, $entities);
    // Sort the queried roles by their weight.
    // See \Drupal\Core\Config\Entity\ConfigEntityBase::sort().
    uasort($entities, 'static::sort');
  }

  /**
   * {@inheritdoc}
   */
  public function preSave(EntityStorageInterface $storage) {
    parent::preSave($storage);

    if (!isset($this->weight) && ($group_roles = $storage->loadMultiple())) {
      // Set a role weight to make this new role last.
      $max = array_reduce($group_roles, function($max, $group_role) {
        return $max > $group_role->weight ? $max : $group_role->weight;
      });

      $this->weight = $max + 1;
    }

    if (!$this->isSyncing()) {
      // Permissions are always ordered alphabetically to avoid conflicts in the
      // exported configuration.
      sort($this->permissions);
    }
  }

}
