<?php

namespace Drupal\group\CoreFix\Cache;

use Drupal\Core\Cache\CacheFactoryInterface;
use Drupal\Core\Cache\MemoryCache\MemoryCache;

/**
 * DO NOT USE! Placeholder for when core commits this properly.
 *
 * @internal
 */
class MemoryCacheFactory implements CacheFactoryInterface {

  /**
   * Instantiated memory cache bins.
   *
   * @var \Drupal\Core\Cache\MemoryCache\MemoryCache[]
   */
  protected $bins = [];

  /**
   * {@inheritdoc}
   */
  public function get($bin) {
    if (!isset($this->bins[$bin])) {
      $this->bins[$bin] = new MemoryCache();
    }
    return $this->bins[$bin];
  }

}
