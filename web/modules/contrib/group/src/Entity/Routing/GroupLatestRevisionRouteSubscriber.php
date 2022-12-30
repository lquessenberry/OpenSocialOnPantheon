<?php

namespace Drupal\group\Entity\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;

/**
 * Swaps out the latest revision page access callback.
 */
class GroupLatestRevisionRouteSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection) {
    if ($route = $collection->get('entity.group.latest_version')) {
      $requirements = $route->getRequirements();
      unset($requirements['_content_moderation_latest_version']);
      $requirements['_group_latest_revision'] = 'TRUE';
      $route->setRequirements($requirements);
    }
  }

}
