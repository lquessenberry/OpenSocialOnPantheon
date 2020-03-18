<?php

namespace Drupal\data_policy;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityHandlerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines the access control handler for the informblock entity type.
 *
 * @see \Drupal\data_policy\Entity\InformBlock
 */
class DataPolicyInformAccessControlHandler extends EntityAccessControlHandler implements EntityHandlerInterface {

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    return new static($entity_type);
  }

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    if ($operation == 'update') {
      $permissions = [
        'administer inform and consent settings',
        'edit inform and consent setting',
      ];

      foreach ($permissions as $permission) {
        if ($account->hasPermission($permission)) {
          return AccessResult::allowed();
        }
      }
    }

    return parent::checkAccess($entity, $operation, $account);
  }

}
