<?php

namespace Drupal\group;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\group\Entity\GroupTypeInterface;
use Drupal\User\RoleInterface;

/**
 * Synchronizes user roles to group roles.
 */
class GroupRoleSynchronizer implements GroupRoleSynchronizerInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a new GroupRoleSynchronizer.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function getGroupRoleId($group_type_id, $role_id) {
    // The maximum length of a group role's machine name.
    //
    // Group role IDs consist of two parts separated by a dash:
    // - The group type ID.
    // - The machine name of the group role; unique per group type.
    //
    // Therefore, the maximum length of a group role machine name is determined
    // by subtracting the group type ID length from the entity type ID length
    // and leaving room for a dash character.
    $machine_name_max_length = EntityTypeInterface::ID_MAX_LENGTH - GroupTypeInterface::ID_MAX_LENGTH - 1;

    // Generate an MD5 hash to use as the group role machine name.
    $machine_name = substr(md5('group_role_sync.' . $role_id), 0, $machine_name_max_length);

    return "$group_type_id-$machine_name";
  }

  /**
   * {@inheritdoc}
   */
  public function getGroupRoleIdsByGroupType($group_type_id) {
    return $this->getGroupRoleIdsByGroupTypes([$group_type_id]);
  }

  /**
   * {@inheritdoc}
   */
  public function getGroupRoleIdsByGroupTypes($group_type_ids) {
    $group_role_ids = [];

    $role_ids = $this->entityTypeManager->getStorage('user_role')->getQuery()->execute();
    foreach ($role_ids as $role_id) {
      foreach ($group_type_ids as $group_type_id) {
        $group_role_ids[] = $this->getGroupRoleId($group_type_id, $role_id);
      }
    }

    return $group_role_ids;
  }

  /**
   * {@inheritdoc}
   */
  public function getGroupRoleIdsByUserRole($role_id) {
    return $this->getGroupRoleIdsByUserRoles([$role_id]);
  }

  /**
   * {@inheritdoc}
   */
  public function getGroupRoleIdsByUserRoles($role_ids) {
    $group_role_ids = [];

    $group_type_ids = $this->entityTypeManager->getStorage('group_type')->getQuery()->execute();
    foreach ($group_type_ids as $group_type_id) {
      foreach ($role_ids as $role_id) {
        $group_role_ids[] = $this->getGroupRoleId($group_type_id, $role_id);
      }
    }

    return $group_role_ids;
  }

}
