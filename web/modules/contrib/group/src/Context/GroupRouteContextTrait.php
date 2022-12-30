<?php

namespace Drupal\group\Context;

use Drupal\group\Entity\GroupInterface;

/**
 * Trait to get the group entity from the current route.
 *
 * Using this trait will add the getGroupFromRoute() method to the class.
 *
 * If the class is capable of injecting services from the container, it should
 * inject the 'current_route_match' and 'entity_type.manager' services and
 * assign them to the currentRouteMatch and entityTypeManager properties.
 */
trait GroupRouteContextTrait {

  /**
   * The current route match object.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $currentRouteMatch;

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Gets the current route match object.
   *
   * @return \Drupal\Core\Routing\RouteMatchInterface
   *   The current route match object.
   */
  protected function getCurrentRouteMatch() {
    if (!$this->currentRouteMatch) {
      $this->currentRouteMatch = \Drupal::service('current_route_match');
    }
    return $this->currentRouteMatch;
  }

  /**
   * Gets the entity type manager service.
   *
   * @return \Drupal\Core\Entity\EntityTypeManagerInterface
   *   The entity type manager service.
   */
  protected function getEntityTypeManager() {
    if (!$this->entityTypeManager) {
      $this->entityTypeManager = \Drupal::entityTypeManager();
    }
    return $this->entityTypeManager;
  }

  /**
   * Retrieves the group entity from the current route.
   *
   * This will try to load the group entity from the route if present. If we are
   * on the group add form, it will return a new group entity with the group
   * type set.
   *
   * @return \Drupal\group\Entity\GroupInterface|null
   *   A group entity if one could be found or created, NULL otherwise.
   */
  public function getGroupFromRoute() {
    $route_match = $this->getCurrentRouteMatch();

    // See if the route has a group parameter and try to retrieve it.
    if (($group = $route_match->getParameter('group')) && $group instanceof GroupInterface) {
      return $group;
    }
    // Create a new group to use as context if on the group add form.
    elseif ($route_match->getRouteName() == 'entity.group.add_form') {
      $group_type = $route_match->getParameter('group_type');
      return $this->getEntityTypeManager()->getStorage('group')->create(['type' => $group_type->id()]);
    }

    return NULL;
  }

}
