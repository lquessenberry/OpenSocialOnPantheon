<?php

namespace Drupal\image_effects\Component;

use Drupal\Component\Utility\Color;
use Drupal\Component\Utility\Unicode;

/**
 * Color handling methods for image_effects.
 */
abstract class ColorUtility {

  /**
   * Determine best match to over/underlay a defined color.
   *
   * Calculates UCCIR 601 luma of the entered color and returns a black or
   * white color to ensure readibility.
   *
   * @see http://en.wikipedia.org/wiki/Luma_video
   */
  public static function matchLuma($rgba, $soft = FALSE) {
    $rgb = Unicode::substr($rgba, 0, 7);
    list($r, $g, $b) = array_values(Color::hexToRgb($rgb));
    $luma = 1 - (0.299 * $r + 0.587 * $g + 0.114 * $b) / 255;
    if ($luma < 0.5) {
      // Bright colors - black.
      $d = 0;
    }
    else {
      // Dark colors - white.
      $d = 255;
    }
    return Color::rgbToHex([$d, $d, $d]);
  }

  /**
   * Convert RGBA alpha to percent opacity.
   *
   * @param string $rgba
   *   RGBA hexadecimal.
   *
   * @return int
   *   Opacity as percentage (0 = transparent, 100 = fully opaque).
   */
  public static function rgbaToOpacity($rgba) {
    $hex = Unicode::substr($rgba, 7, 2);
    return $hex ? floor(hexdec($hex) / 255 * 100) : 100;
  }

  /**
   * Convert percent opacity to hex alpha.
   *
   * @param int $value
   *   Opacity as percentage (0 = transparent, 100 = fully opaque).
   *
   * @return string|null
   *   Opacity as HEX (#00 = transparent, #FF = fully opaque).
   */
  public static function opacityToAlpha($value) {
    if (!$value || $value < 0 || $value > 100) {
      return NULL;
    }
    return Unicode::strtoupper(str_pad(dechex(ceil($value / 100 * 255)), 2, '0', STR_PAD_LEFT));
  }

}
