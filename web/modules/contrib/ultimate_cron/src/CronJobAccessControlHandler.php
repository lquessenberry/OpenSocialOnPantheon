<?php

namespace Drupal\ultimate_cron;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Defines a class to check whether a cron job is valid and should be deletable.
 */
class CronJobAccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    if ($operation === 'delete') {
      if (!$entity->isValid()) {
        return AccessResult::allowedIfHasPermission($account, 'administer ultimate cron');
      }
      return AccessResult::forbidden();
    }
    if ($operation === 'update') {
      if ($entity->isValid()) {
        return AccessResult::allowedIfHasPermission($account, 'administer ultimate cron');
      }
      return AccessResult::forbidden();
    }
    return parent::checkAccess($entity, $operation, $account);
  }
}
