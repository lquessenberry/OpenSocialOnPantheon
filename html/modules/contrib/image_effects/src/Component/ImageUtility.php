<?php

namespace Drupal\image_effects\Component;

/**
 * Image handling methods for image_effects.
 */
abstract class ImageUtility {

  /**
   * Computes a length based on a length specification and an actual length.
   *
   * Examples:
   *  (50, 400) returns 50; (50%, 400) returns 200;
   *  (50, null) returns 50; (50%, null) returns null;
   *  (null, null) returns null; (null, 100) returns null.
   *
   * @param string|null $length_specification
   *   The length specification. An integer constant or a % specification.
   * @param int|null $current_length
   *   The current length. May be null.
   *
   * @return int|null
   *   The computed length.
   */
  public static function percentFilter($length_specification, $current_length) {
    if (strpos($length_specification, '%') !== FALSE) {
      $length_specification = $current_length !== NULL ? str_replace('%', '', $length_specification) * 0.01 * $current_length : NULL;
    }
    return $length_specification;
  }

}
