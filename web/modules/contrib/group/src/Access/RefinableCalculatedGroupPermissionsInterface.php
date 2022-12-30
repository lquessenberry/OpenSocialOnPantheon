<?php

namespace Drupal\group\Access;

use Drupal\Core\Cache\RefinableCacheableDependencyInterface;

/**
 * Defines the calculated group permissions interface.
 */
interface RefinableCalculatedGroupPermissionsInterface extends RefinableCacheableDependencyInterface, CalculatedGroupPermissionsInterface {

  /**
   * Adds a calculated permission item.
   *
   * @param \Drupal\group\Access\CalculatedGroupPermissionsItemInterface $item
   *   The calculated permission item.
   * @param bool $overwrite
   *   (optional) Whether to overwrite an item if there already is one for the
   *   given identifier within the scope. Defaults to FALSE, meaning a merge
   *   will take place instead.
   *
   * @return $this
   */
  public function addItem(CalculatedGroupPermissionsItemInterface $item, $overwrite = FALSE);

  /**
   * Removes a single calculated permission item from a given scope.
   *
   * @param $scope
   *   The scope name to remove the item from.
   * @param $identifier
   *   The scope identifier to remove the item from.
   *
   * @return $this
   */
  public function removeItem($scope, $identifier);

  /**
   * Removes all of the calculated permission items, regardless of scope.
   *
   * @return $this
   */
  public function removeItems();

  /**
   * Removes all of the calculated permission items for the given scope.
   *
   * @param string $scope
   *   The scope name to remove the items for.
   *
   * @return $this
   */
  public function removeItemsByScope($scope);

  /**
   * Merge another calculated group permissions object into this one.
   *
   * This merges (not replaces) all permissions and cacheable metadata.
   *
   * @param \Drupal\group\Access\CalculatedGroupPermissionsInterface $other
   *   The other calculated group permissions object to merge into this one.
   *
   * @return $this
   */
  public function merge(CalculatedGroupPermissionsInterface $other);

}
