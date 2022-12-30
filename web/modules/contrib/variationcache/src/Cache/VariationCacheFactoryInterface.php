<?php

namespace Drupal\variationcache\Cache;

/**
 * Defines an interface for variation cache implementations.
 *
 * A variation cache wraps any provided cache backend and adds support for cache
 * contexts to it. The actual caching still happens in the original cache
 * backend.
 *
 * @ingroup cache
 */
interface VariationCacheFactoryInterface {

  /**
   * Gets a variation cache backend for a given cache bin.
   *
   * @param string $bin
   *   The cache bin for which a variation cache backend should be returned.
   *
   * @return \Drupal\variationcache\Cache\VariationCacheInterface
   *   The variation cache backend associated with the specified bin.
   */
  public function get($bin);

}
