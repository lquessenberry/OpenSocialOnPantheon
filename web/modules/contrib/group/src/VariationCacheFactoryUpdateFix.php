<?php

namespace Drupal\group;

/**
 * Throws an exception when the VariationCache module is not installed.
 */
class VariationCacheFactoryUpdateFix {

  /**
   * {@inheritdoc}
   */
  public function get($bin) {
    throw new \Exception('The "VariationCache" module is not installed. Please run the update script to install it properly.');
  }

}
