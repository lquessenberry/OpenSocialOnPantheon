<?php

namespace Drupal\group\Access;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\group\GroupMembershipLoaderInterface;

/**
 * Calculates group permissions for an account.
 */
class DefaultGroupPermissionCalculator extends GroupPermissionCalculatorBase {

  /**
   * The member roles depend on which memberships you have, for which we do not
   * currently have a dedicated cache context as it has a very high granularity.
   * We therefore cache the calculated permissions per user.
   */
  const MEMBER_CACHE_CONTEXTS = ['user'];

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The membership loader service.
   *
   * @var \Drupal\group\GroupMembershipLoaderInterface
   */
  protected $membershipLoader;

  /**
   * Constructs a DefaultGroupPermissionCalculator object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\group\GroupMembershipLoaderInterface $membership_loader
   *   The group membership loader service.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, GroupMembershipLoaderInterface $membership_loader) {
    $this->entityTypeManager = $entity_type_manager;
    $this->membershipLoader = $membership_loader;
  }

  /**
   * {@inheritdoc}
   */
  public function calculateAnonymousPermissions() {
    $calculated_permissions = new RefinableCalculatedGroupPermissions();

    // @todo Introduce group_role_list:audience:anonymous cache tag.
    // If a new group type is introduced, we need to recalculate the anonymous
    // permissions hash. Therefore, we need to introduce the group type list
    // cache tag.
    $calculated_permissions->addCacheTags(['config:group_type_list']);

    /** @var \Drupal\group\Entity\GroupTypeInterface $group_type */
    $storage = $this->entityTypeManager->getStorage('group_type');
    foreach ($storage->loadMultiple() as $group_type_id => $group_type) {
      $group_role = $group_type->getAnonymousRole();

      $item = new CalculatedGroupPermissionsItem(
        CalculatedGroupPermissionsItemInterface::SCOPE_GROUP_TYPE,
        $group_type_id,
        $group_role->getPermissions()
      );

      $calculated_permissions->addItem($item);
      $calculated_permissions->addCacheableDependency($group_role);
    }

    return $calculated_permissions;
  }

  /**
   * {@inheritdoc}
   */
  public function calculateOutsiderPermissions(AccountInterface $account) {
    $calculated_permissions = new RefinableCalculatedGroupPermissions();

    // @todo Introduce group_role_list:audience:outsider cache tag.
    // If a new group type is introduced, we need to recalculate the outsider
    // permissions. Therefore, we need to introduce the group type list cache
    // tag.
    $calculated_permissions->addCacheTags(['config:group_type_list']);

    /** @var \Drupal\group\Entity\GroupTypeInterface $group_type */
    $storage = $this->entityTypeManager->getStorage('group_type');
    foreach ($storage->loadMultiple() as $group_type_id => $group_type) {
      $group_role = $group_type->getOutsiderRole();

      $item = new CalculatedGroupPermissionsItem(
        CalculatedGroupPermissionsItemInterface::SCOPE_GROUP_TYPE,
        $group_type_id,
        $group_role->getPermissions()
      );

      $calculated_permissions->addItem($item);
      $calculated_permissions->addCacheableDependency($group_role);
    }

    return $calculated_permissions;
  }

  /**
   * {@inheritdoc}
   */
  public function calculateMemberPermissions(AccountInterface $account) {
    $calculated_permissions = new RefinableCalculatedGroupPermissions();

    // The member permissions need to be recalculated whenever the user is added
    // to or removed from a group.
    $calculated_permissions->addCacheTags(['group_content_list:plugin:group_membership:entity:' . $account->id()]);

    foreach ($this->membershipLoader->loadByUser($account) as $group_membership) {
      // If the member's roles change, so do the permissions.
      $calculated_permissions->addCacheableDependency($group_membership);

      $permission_sets = [];
      foreach ($group_membership->getRoles() as $group_role) {
        $permission_sets[] = $group_role->getPermissions();
        $calculated_permissions->addCacheableDependency($group_role);
      }

      $permissions = $permission_sets ? array_merge(...$permission_sets) : [];
      $item = new CalculatedGroupPermissionsItem(
        CalculatedGroupPermissionsItemInterface::SCOPE_GROUP,
        $group_membership->getGroup()->id(),
        $permissions
      );

      $calculated_permissions->addItem($item);
    }

    return $calculated_permissions;
  }

}
