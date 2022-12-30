<?php

namespace Drupal\group\Entity\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;

/**
 * Swaps out the revision UI access callbacks.
 */
class GroupRevisionRouteSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection) {
    if ($route = $collection->get('entity.group.revision')) {
      $requirements = $route->getRequirements();
      unset($requirements['_entity_access_revision']);
      $requirements['_group_revision'] = 'view';
      $route->setRequirements($requirements);
      $route->setOption('_group_operation_route', TRUE);
    }

    if ($route = $collection->get('entity.group.revision_revert_form')) {
      $requirements = $route->getRequirements();
      unset($requirements['_entity_access_revision']);
      $requirements['_group_revision'] = 'update';
      $route->setRequirements($requirements);
      $route->setDefault('_form', '\Drupal\group\Entity\Form\GroupRevisionRevertForm');
      $route->setOption('_group_operation_route', TRUE);
    }

    if ($route = $collection->get('entity.group.version_history')) {
      $requirements = $route->getRequirements();
      unset($requirements['_entity_access_revision']);
      $requirements['_group_revision'] = 'list';
      $route->setRequirements($requirements);
      $route->setDefault('_controller', '\Drupal\group\Entity\Controller\GroupRevisionOverviewController::revisionOverviewController');
      $route->setOption('_group_operation_route', TRUE);
    }
  }

}
