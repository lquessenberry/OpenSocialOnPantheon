<?php

namespace Drupal\group\Access;

/**
 * Trait for \Drupal\group\Access\CalculatedGroupPermissionsInterface.
 */
trait CalculatedGroupPermissionsTrait {

  /**
   * A list of calculated group permission items, keyed by scope and identifier.
   *
   * @var array
   */
  protected $items = [];

  /**
   * {@inheritdoc}
   */
  public function getItem($scope, $identifier) {
    return isset($this->items[$scope][$identifier])
      ? $this->items[$scope][$identifier]
      : FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function getItems() {
    $items = [];
    foreach ($this->items as $scope_items) {
      foreach ($scope_items as $item) {
        $items[] = $item;
      }
    }
    return $items;
  }

  /**
   * {@inheritdoc}
   */
  public function getItemsByScope($scope) {
    return isset($this->items[$scope])
      ? array_values($this->items[$scope])
      : [];
  }

}
