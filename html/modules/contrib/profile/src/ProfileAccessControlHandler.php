<?php

namespace Drupal\profile;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Session\AccountInterface;
use Drupal\entity\EntityAccessControlHandler as EntityApiAccessControlHandler;
use Drupal\profile\Entity\ProfileType;

/**
 * Defines the access control handler for the profile entity type.
 *
 * @see \Drupal\profile\Entity\Profile
 */
class ProfileAccessControlHandler extends EntityApiAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL) {
    $result = parent::checkCreateAccess($account, $context, $entity_bundle);

    // If access is allowed, check role restriction.
    if ($result->isAllowed()) {
      $bundle = ProfileType::load($entity_bundle);
      if (!empty(array_filter($bundle->getRoles()))) {
        $result = AccessResult::allowedIf(!empty(array_intersect($account->getRoles(), $bundle->getRoles())));
      }
    }

    return $result;
  }

}
