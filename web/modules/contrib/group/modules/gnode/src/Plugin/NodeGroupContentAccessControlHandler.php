<?php

namespace Drupal\gnode\Plugin;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\group\Plugin\GroupContentAccessControlHandler;

/**
 * Override group's ACH to allow fallback to normal node_access handling.
 *
 * See https://www.drupal.org/project/group/issues/3162511.
 */
class NodeGroupContentAccessControlHandler extends GroupContentAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  public function entityAccess(EntityInterface $entity, $operation, AccountInterface $account, $return_as_object = FALSE) {
    $result = parent::entityAccess($entity, $operation, $account, TRUE);

    // We're NOT returning forbidden here because we want to fall back to normal
    // node_access handling. node grants only deal with view update and delete.
    if ($result->isForbidden() && in_array($operation, ['view', 'update', 'delete'])) {
      $result = AccessResult::neutral()->addCacheableDependency($result);
    }

    return $return_as_object ? $result : $result->isAllowed();
  }

}
