<?php

namespace Drupal\profile\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\Routing\Route;
use Drupal\profile\Entity\ProfileTypeInterface;

/**
 * Checks access to add, edit and delete profiles.
 */
class ProfileAccessCheck implements AccessInterface {

  /**
   * The entity manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a ProfileAccessCheck object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * Checks access to the profile add page for the profile type.
   *
   * @param \Symfony\Component\Routing\Route $route
   *   The route to check against.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The currently logged in account.
   * @param \Drupal\Core\Session\AccountInterface $user
   *   The user entity.
   * @param \Drupal\profile\Entity\ProfileTypeInterface $profile_type
   *   The profile type entity.
   *
   * @return bool|\Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access(Route $route, AccountInterface $account, AccountInterface $user, ProfileTypeInterface $profile_type) {
    if ($account->hasPermission('administer profile types')) {
      return AccessResult::allowed()->cachePerPermissions();
    }
    $own_any = ($account->id() == $user->id()) ? 'own' : 'any';
    $operation = ($profile_type->getMultiple()) ? 'view' : 'update';

    return AccessResult::allowedIfHasPermission($account, "$operation $own_any {$profile_type->id()} profile");
  }

}
