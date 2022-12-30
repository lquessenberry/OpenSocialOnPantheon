<?php

namespace Drupal\group\Access;

use Drupal\Core\Cache\CacheableDependencyTrait;

/**
 * Represents a calculated set of group permissions with cacheable metadata.
 *
 * @see \Drupal\group\Access\ChainGroupPermissionCalculator
 */
class CalculatedGroupPermissions implements CalculatedGroupPermissionsInterface {

  use CacheableDependencyTrait;
  use CalculatedGroupPermissionsTrait;

  /**
   * Constructs a new CalculatedGroupPermissions.
   *
   * @param \Drupal\group\Access\CalculatedGroupPermissionsInterface $source
   *   The calculated group permission to create a value object from.
   */
  public function __construct(CalculatedGroupPermissionsInterface $source) {
    foreach ($source->getItems() as $item) {
      $this->items[$item->getScope()][$item->getIdentifier()] = $item;
    }
    $this->setCacheability($source);
  }

}
