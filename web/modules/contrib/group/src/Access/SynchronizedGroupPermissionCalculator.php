<?php

namespace Drupal\group\Access;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\group\GroupRoleSynchronizerInterface;

/**
 * Calculates group permissions for an account.
 */
class SynchronizedGroupPermissionCalculator extends GroupPermissionCalculatorBase {

  /**
   * The synchronized roles depend on which user roles you have, so we need to
   * vary the calculated permissions by the user.roles cache context.
   */
  const OUTSIDER_CACHE_CONTEXTS = ['user.roles'];

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The group role synchronizer service.
   *
   * @var \Drupal\group\GroupRoleSynchronizerInterface
   */
  protected $groupRoleSynchronizer;

  /**
   * Constructs a SynchronizedGroupPermissionCalculator object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\group\GroupRoleSynchronizerInterface $group_role_synchronizer
   *   The group role synchronizer service.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, GroupRoleSynchronizerInterface $group_role_synchronizer) {
    $this->entityTypeManager = $entity_type_manager;
    $this->groupRoleSynchronizer = $group_role_synchronizer;
  }

  /**
   * {@inheritdoc}
   */
  public function calculateOutsiderPermissions(AccountInterface $account) {
    $calculated_permissions = new RefinableCalculatedGroupPermissions();
    $group_type_storage = $this->entityTypeManager->getStorage('group_type');
    $group_role_storage = $this->entityTypeManager->getStorage('group_role');
    $roles = $account->getRoles(TRUE);

    foreach ($group_type_storage->getQuery()->execute() as $group_type_id) {
      $permission_sets = [];

      $group_role_ids = [];
      foreach ($roles as $role_id) {
        $group_role_ids[] = $this->groupRoleSynchronizer->getGroupRoleId($group_type_id, $role_id);
      }

      if (!empty($group_role_ids)) {
        /** @var \Drupal\group\Entity\GroupRoleInterface $group_role */
        foreach ($group_role_storage->loadMultiple($group_role_ids) as $group_role) {
          $permission_sets[] = $group_role->getPermissions();
          $calculated_permissions->addCacheableDependency($group_role);
        }
      }

      $permissions = $permission_sets ? array_merge(...$permission_sets) : [];
      $item = new CalculatedGroupPermissionsItem(
        CalculatedGroupPermissionsItemInterface::SCOPE_GROUP_TYPE,
        $group_type_id,
        $permissions
      );

      $calculated_permissions->addItem($item);
    }

    return $calculated_permissions;
  }

}
