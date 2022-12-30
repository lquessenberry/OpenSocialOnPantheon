<?php

namespace Drupal\group\Entity\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\group\Access\GroupAccessResult;

/**
 * Access controller for the Group entity.
 *
 * @see \Drupal\group\Entity\Group.
 */
class GroupAccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    /** @var \Drupal\group\Entity\GroupInterface $entity */
    switch ($operation) {
      case 'view':
        if (!$entity->isPublished()) {
          $access_result = GroupAccessResult::allowedIfHasGroupPermission($entity, $account, 'view any unpublished group');
          if (!$access_result->isAllowed() && $account->isAuthenticated() && $account->id() === $entity->getOwnerId()) {
            $access_result = GroupAccessResult::allowedIfHasGroupPermission($entity, $account, 'view own unpublished group')->cachePerUser();
          }
        }
        else {
          $access_result = GroupAccessResult::allowedIfHasGroupPermission($entity, $account, 'view group');
        }

        // The access result might change if group status changes.
        return $access_result->addCacheableDependency($entity);

      case 'update':
        return GroupAccessResult::allowedIfHasGroupPermission($entity, $account, 'edit group');

      case 'delete':
        return GroupAccessResult::allowedIfHasGroupPermission($entity, $account, 'delete group');
    }

    return AccessResult::neutral();
  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL) {
    $permissions = ['bypass group access', 'create ' . $entity_bundle . ' group'];
    return AccessResult::allowedIfHasPermissions($account, $permissions, 'OR');
  }

}
