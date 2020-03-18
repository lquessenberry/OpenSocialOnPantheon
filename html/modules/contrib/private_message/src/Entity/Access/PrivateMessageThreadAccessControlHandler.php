<?php

namespace Drupal\private_message\Entity\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Access control handler for private message thread entities.
 */
class PrivateMessageThreadAccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    if ($account->hasPermission('use private messaging system')) {
      switch ($operation) {
        case 'view':
          if ($entity->isMember($account->id())) {
            $messages = $entity->filterUserDeletedMessages($account);
            if (count($messages)) {
              return AccessResult::allowed();
            }
          }

          break;

        case 'delete':
          if ($entity->isMember($account->id())) {
            return AccessResult::allowed();
          }

          break;
      }
    }

    return AccessResult::forbidden();
  }

  /**
   * {@inheritdoc}
   *
   * Separate from the checkAccess because the entity does not yet exist, it
   * will be created during the 'add' process.
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL) {
    return AccessResult::allowedIfHasPermission($account, 'use private messaging system');
  }

}
