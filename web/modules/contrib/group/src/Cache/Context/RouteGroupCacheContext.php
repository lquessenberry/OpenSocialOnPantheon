<?php

namespace Drupal\group\Cache\Context;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Cache\Context\CacheContextInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\group\Context\GroupRouteContextTrait;

/**
 * Defines a cache context for "per group from route" caching.
 *
 * Please note: This cache context uses the group from the current route as the
 * context value to work with. This context is therefore only to be used with
 * data that was based on the group from the route. A good example being the
 * 'entity:group' context provided by the 'group.group_route_context' service.
 *
 * Cache context ID: 'route.group'.
 */
class RouteGroupCacheContext implements CacheContextInterface {

  use GroupRouteContextTrait;

  /**
   * Constructs a new RouteGroupCacheContext.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $current_route_match
   *   The current route match object.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(RouteMatchInterface $current_route_match, EntityTypeManagerInterface $entity_type_manager) {
    $this->currentRouteMatch = $current_route_match;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function getLabel() {
    return t('Group from route');
  }

  /**
   * {@inheritdoc}
   */
  public function getContext() {
    if ($group = $this->getGroupFromRoute()) {
      // If a group was found on the route, we return its ID as the context.
      if ($gid = $group->id()) {
        return $gid;
      }
      // If there was no ID, but we still have a group, we are on the group add
      // form and we return the group type instead. This allows the context to
      // distinguish between unsaved groups, based on their type.
      return $group->bundle();
    }

    // If no group was found on the route, we return a string that cannot be
    // mistaken for a group ID or group type.
    return 'group.none';
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheableMetadata() {
    return new CacheableMetadata();
  }

}
