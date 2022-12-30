<?php

namespace Drupal\lazy;

/**
 * Interface for Lazy-load service.
 */
interface LazyInterface {

  /**
   * Get Lazy module settings.
   *
   * @return array
   *   Settings array.
   */
  public function getSettings(): array;

  /**
   * List of available Lazysizes plugins.
   *
   * @return array
   *   Returns an array of all available lazysizes plugins.
   */
  public function getPlugins(): array;

  /**
   * Is Lazy-load enabled?
   *
   * @param array $attributes
   *   Element attributes, specifically for the "class".
   *
   * @return bool
   *   Returns true if the path is not restricted, and skip class is not set.
   *   FALSE otherwise.
   */
  public function isEnabled(array $attributes = []): bool;

  /**
   * Is lazy-loading allowed for current path?
   *
   * @return bool
   *   Returns TRUE if lazy-loading is allowed for current path.
   */
  public function isPathAllowed(): bool;

}
