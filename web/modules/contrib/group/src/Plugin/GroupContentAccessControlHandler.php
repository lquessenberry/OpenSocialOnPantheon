<?php

namespace Drupal\group\Plugin;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityPublishedInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\group\Access\GroupAccessResult;
use Drupal\group\Entity\GroupContentInterface;
use Drupal\group\Entity\GroupInterface;
use Drupal\user\EntityOwnerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides access control for GroupContent entities and grouped entities.
 */
class GroupContentAccessControlHandler extends GroupContentHandlerBase implements GroupContentAccessControlHandlerInterface {

  /**
   * The plugin's permission provider.
   *
   * @var \Drupal\group\Plugin\GroupContentPermissionProviderInterface
   */
  protected $permissionProvider;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, $plugin_id, array $definition) {
    /** @var \Drupal\group\Plugin\GroupContentEnablerManagerInterface $manager */
    $manager = $container->get('plugin.manager.group_content_enabler');
    if (!$manager->hasHandler($plugin_id, 'permission_provider')) {
      throw new \LogicException('Cannot use an "access" handler without a "permission_provider" handler.');
    }

    /** @var static $instance */
    $instance = parent::createInstance($container, $plugin_id, $definition);
    $instance->permissionProvider = $manager->getPermissionProvider($plugin_id);
    $instance->entityTypeManager = $container->get('entity_type.manager');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function relationAccess(GroupContentInterface $group_content, $operation, AccountInterface $account, $return_as_object = FALSE) {
    $result = AccessResult::neutral();

    // Check if the account is the owner.
    $is_owner = $group_content->getOwnerId() === $account->id();

    // Add in the admin permission and filter out the unsupported permissions.
    $permissions = [$this->permissionProvider->getAdminPermission()];
    $permissions[] = $this->permissionProvider->getPermission($operation, 'relation', 'any');
    $own_permission = $this->permissionProvider->getPermission($operation, 'relation', 'own');
    if ($is_owner) {
      $permissions[] = $own_permission;
    }
    $permissions = array_filter($permissions);

    // If we still have permissions left, check for access.
    if (!empty($permissions)) {
      $result = GroupAccessResult::allowedIfHasGroupPermissions($group_content->getGroup(), $account, $permissions, 'OR');
    }

    // If there was an owner permission to check, the result needs to vary per
    // user. We also need to add the relation as a dependency because if its
    // owner changes, someone might suddenly gain or lose access.
    if ($own_permission) {
      // @todo Not necessary if admin, could boost performance here.
      $result->cachePerUser()->addCacheableDependency($group_content);
    }

    return $return_as_object ? $result : $result->isAllowed();
  }

  /**
   * {@inheritdoc}
   */
  public function relationCreateAccess(GroupInterface $group, AccountInterface $account, $return_as_object = FALSE) {
    $permission = $this->permissionProvider->getRelationCreatePermission();
    return $this->combinedPermissionCheck($group, $account, $permission, $return_as_object);
  }

  /**
   * {@inheritdoc}
   */
  public function entityAccess(EntityInterface $entity, $operation, AccountInterface $account, $return_as_object = FALSE) {
    /** @var \Drupal\group\Entity\Storage\GroupContentStorageInterface $storage */
    $storage = $this->entityTypeManager->getStorage('group_content');
    $group_contents = $storage->loadByEntity($entity);

    // Filter out the content that does not use this plugin.
    foreach ($group_contents as $id => $group_content) {
      // @todo Shows the need for a plugin ID base field.
      $plugin_id = $group_content->getContentPlugin()->getPluginId();
      if ($plugin_id !== $this->pluginId) {
        unset($group_contents[$id]);
      }
    }

    // If this plugin is not being used by the entity, we have nothing to say.
    if (empty($group_contents)) {
      return AccessResult::neutral();
    }

    // We only check unpublished vs published for "view" right now. If we ever
    // start supporting other operations, we need to remove the "view" check.
    $check_published = $operation === 'view'
      && $entity->getEntityType()->entityClassImplements(EntityPublishedInterface::class);

    // Check if the account is the owner and an owner permission is supported.
    $is_owner = FALSE;
    if ($entity->getEntityType()->entityClassImplements(EntityOwnerInterface::class)) {
      $is_owner = $entity->getOwnerId() === $account->id();
    }

    // Add in the admin permission and filter out the unsupported permissions.
    $permissions = [$this->permissionProvider->getAdminPermission()];
    if (!$check_published || $entity->isPublished()) {
      $permissions[] = $this->permissionProvider->getPermission($operation, 'entity', 'any');
      $own_permission = $this->permissionProvider->getPermission($operation, 'entity', 'own');
      if ($is_owner) {
        $permissions[] = $own_permission;
      }
    }
    elseif ($check_published && !$entity->isPublished()) {
      $permissions[] = $this->permissionProvider->getPermission("$operation unpublished", 'entity', 'any');
      $own_permission = $this->permissionProvider->getPermission("$operation unpublished", 'entity', 'own');
      if ($is_owner) {
        $permissions[] = $own_permission;
      }
    }
    $permissions = array_filter($permissions);

    foreach ($group_contents as $group_content) {
      $result = GroupAccessResult::allowedIfHasGroupPermissions($group_content->getGroup(), $account, $permissions, 'OR');
      if ($result->isAllowed()) {
        break;
      }
    }

    // If we did not allow access, we need to explicitly forbid access to avoid
    // other modules from granting access where Group promised the entity would
    // be inaccessible.
    if (!$result->isAllowed()) {
      $result = AccessResult::forbidden()->addCacheContexts(['user.group_permissions']);
    }

    // If there was an owner permission to check, the result needs to vary per
    // user. We also need to add the entity as a dependency because if its owner
    // changes, someone might suddenly gain or lose access.
    if (!empty($own_permission)) {
      // @todo Not necessary if admin, could boost performance here.
      $result->cachePerUser();
    }

    // If we needed to check for the owner permission or published access, we
    // need to add the entity as a dependency because the owner or publication
    // status might change.
    if (!empty($own_permission) || $check_published) {
      // @todo Not necessary if admin, could boost performance here.
      $result->addCacheableDependency($entity);
    }

    return $return_as_object ? $result : $result->isAllowed();
  }

  /**
   * {@inheritdoc}
   */
  public function entityCreateAccess(GroupInterface $group, AccountInterface $account, $return_as_object = FALSE) {
    // You cannot create target entities if the plugin does not support it.
    if (empty($this->definition['entity_access'])) {
      return $return_as_object ? AccessResult::neutral() : FALSE;
    }

    $permission = $this->permissionProvider->getEntityCreatePermission();
    return $this->combinedPermissionCheck($group, $account, $permission, $return_as_object);
  }

  /**
   * Checks the provided permission alongside the admin permission.
   *
   * Important: Only one permission needs to match.
   *
   * @param \Drupal\group\Entity\GroupInterface $group
   *   The group to check for access.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user for which to check access.
   * @param string $permission
   *   The names of the permission to check for.
   * @param bool $return_as_object
   *   Whether to return the result as an object or boolean.
   *
   * @return bool|\Drupal\Core\Access\AccessResult
   *   The access result. Returns a boolean if $return_as_object is FALSE (this
   *   is the default) and otherwise an AccessResultInterface object.
   *   When a boolean is returned, the result of AccessInterface::isAllowed() is
   *   returned, i.e. TRUE means access is explicitly allowed, FALSE means
   *   access is either explicitly forbidden or "no opinion".
   */
  protected function combinedPermissionCheck(GroupInterface $group, AccountInterface $account, $permission, $return_as_object) {
    $result = AccessResult::neutral();

    // Add in the admin permission and filter out the unsupported permissions.
    $permissions = [$permission, $this->permissionProvider->getAdminPermission()];
    $permissions = array_filter($permissions);

    // If we still have permissions left, check for access.
    if (!empty($permissions)) {
      $result = GroupAccessResult::allowedIfHasGroupPermissions($group, $account, $permissions, 'OR');
    }

    return $return_as_object ? $result : $result->isAllowed();
  }

}
