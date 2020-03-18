<?php

namespace Drupal\entity\Routing;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\Routing\DefaultHtmlRouteProvider as CoreDefaultHtmlRouteProvider;

/**
 * Provides HTML routes for entities.
 */
class DefaultHtmlRouteProvider extends CoreDefaultHtmlRouteProvider {

  /**
   * {@inheritdoc}
   */
  protected function getCollectionRoute(EntityTypeInterface $entity_type) {
    $route = parent::getCollectionRoute($entity_type);
    if ($route && $entity_type->hasHandlerClass('permission_provider')) {
      $admin_permission = $entity_type->getAdminPermission();
      $overview_permission = "access {$entity_type->id()} overview";
      $route->setRequirement('_permission', "$admin_permission+$overview_permission");
    }
    return $route;
  }

}
