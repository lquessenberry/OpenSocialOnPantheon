<?php

namespace Drupal\group\Access;

use Drupal\Core\Cache\CacheableDependencyInterface;

/**
 * Defines the calculated group permissions interface.
 */
interface CalculatedGroupPermissionsInterface extends CacheableDependencyInterface {

  /**
   * Retrieves a single calculated permission item from a given scope.
   *
   * @param $scope
   *   The scope name to retrieve the item for.
   * @param $identifier
   *   The scope identifier to retrieve the item for.
   *
   * @return \Drupal\group\Access\CalculatedGroupPermissionsItemInterface|false
   *   The calculated permission item or FALSE if it could not be found.
   */
  public function getItem($scope, $identifier);

  /**
   * Retrieves all of the calculated permission items, regardless of scope.
   *
   * @return \Drupal\group\Access\CalculatedGroupPermissionsItemInterface[]
   *   A list of calculated permission items.
   */
  public function getItems();

  /**
   * Retrieves all of the calculated permission items for the given scope.
   *
   * @param string $scope
   *   The scope name to retrieve the items for.
   *
   * @return \Drupal\group\Access\CalculatedGroupPermissionsItemInterface[]
   *   A list of calculated permission items for the given scope.
   */
  public function getItemsByScope($scope);

}
