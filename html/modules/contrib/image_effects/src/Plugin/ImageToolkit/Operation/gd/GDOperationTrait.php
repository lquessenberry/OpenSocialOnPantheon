<?php

namespace Drupal\image_effects\Plugin\ImageToolkit\Operation\gd;

use Drupal\Component\Utility\Color;
use Drupal\Component\Utility\Unicode;
use Drupal\image_effects\Component\ColorUtility;
use Drupal\image_effects\Component\GdGaussianBlur;
use Drupal\image_effects\Component\PositionedRectangle;

/**
 * Trait for GD image toolkit operations.
 */
trait GDOperationTrait {

  /**
   * Allocates a GD color from an RGBA hexadecimal.
   *
   * @param string $rgba_hex
   *   A string specifing an RGBA color in the format '#RRGGBBAA'.
   *
   * @return int
   *   A GD color index.
   */
  protected function allocateColorFromRgba($rgba_hex) {
    list($r, $g, $b, $alpha) = array_values($this->hexToRgba($rgba_hex));
    return imagecolorallocatealpha($this->getToolkit()->getResource(), $r, $g, $b, $alpha);
  }

  /**
   * Convert a RGBA hex to its RGBA integer GD components.
   *
   * GD expects a value between 0 and 127 for alpha, where 0 indicates
   * completely opaque while 127 indicates completely transparent.
   * RGBA hexadecimal notation has #00 for transparent and #FF for
   * fully opaque.
   *
   * @param string $rgba_hex
   *   A string specifing an RGBA color in the format '#RRGGBBAA'.
   *
   * @return array
   *   An array with four elements for red, green, blue, and alpha.
   */
  protected function hexToRgba($rgba_hex) {
    $rgbHex = Unicode::substr($rgba_hex, 0, 7);
    try {
      $rgb = Color::hexToRgb($rgbHex);
      $opacity = ColorUtility::rgbaToOpacity($rgba_hex);
      $alpha = 127 - floor(($opacity / 100) * 127);
      $rgb['alpha'] = $alpha;
      return $rgb;
    }
    catch (\InvalidArgumentException $e) {
      return FALSE;
    }
  }

  /**
   * Convert a rectangle to a sequence of point coordinates.
   *
   * GD requires a simple array of point coordinates in its
   * imagepolygon() function.
   *
   * @param \Drupal\image_effects\Component\PositionedRectangle $rect
   *   A PositionedRectangle object.
   *
   * @return array
   *   A simple array of 8 point coordinates.
   */
  protected function getRectangleCorners(PositionedRectangle $rect) {
    $points = [];
    foreach (['c_d', 'c_c', 'c_b', 'c_a'] as $c) {
      $point = $rect->getPoint($c);
      $points[] = $point[0];
      $points[] = $point[1];
    }
    return $points;
  }

  /**
   * Copy and merge part of an image, preserving alpha.
   *
   * The standard imagecopymerge() function in PHP GD fails to preserve the
   * alpha information of two merged images. This method implements the
   * workaround described in
   * http://php.net/manual/en/function.imagecopymerge.php#92787
   *
   * @param resource $dst_im
   *   Destination image link resource.
   * @param resource $src_im
   *   Source image link resource.
   * @param int $dst_x
   *   X-coordinate of destination point.
   * @param int $dst_y
   *   Y-coordinate of destination point.
   * @param int $src_x
   *   X-coordinate of source point.
   * @param int $src_y
   *   Y-coordinate of source point.
   * @param int $src_w
   *   Source width.
   * @param int $src_h
   *   Source height.
   * @param int $pct
   *   Opacity of the source image in percentage.
   *
   * @return bool
   *   Returns TRUE on success or FALSE on failure.
   *
   * @see http://php.net/manual/en/function.imagecopymerge.php#92787
   */
  protected function imageCopyMergeAlpha($dst_im, $src_im, $dst_x, $dst_y, $src_x, $src_y, $src_w, $src_h, $pct) {
    if ($pct === 100) {
      // Use imagecopy() if opacity is 100%.
      return imagecopy($dst_im, $src_im, $dst_x, $dst_y, $src_x, $src_y, $src_w, $src_h);
    }
    else {
      // If opacity is below 100%, use the approach described in
      // http://php.net/manual/it/function.imagecopymerge.php#92787
      // to preserve watermark alpha.
      // --------------------------------------
      // Create a cut resource.
      // @todo when #2583041 is committed, add a check for memory
      // availability before creating the resource.
      $cut = imagecreatetruecolor($src_w, $src_h);
      if (!is_resource($cut)) {
        return FALSE;
      }

      // Copy relevant section from destination image to the cut resource.
      if (!imagecopy($cut, $dst_im, 0, 0, $dst_x, $dst_y, $src_w, $src_h)) {
        imagedestroy($cut);
        return FALSE;
      }

      // Copy relevant section from merged image to the cut resource.
      if (!imagecopy($cut, $src_im, 0, 0, $src_x, $src_y, $src_w, $src_h)) {
        imagedestroy($cut);
        return FALSE;
      }

      // Insert cut resource to destination image.
      $success = imagecopymerge($dst_im, $cut, $dst_x, $dst_y, 0, 0, $src_w, $src_h, $pct);
      imagedestroy($cut);
      return $success;
    }
  }

  /**
   * Wrapper of imagettftext().
   *
   * If imagettftext() is missing, throw an exception instead of failing
   * fatally.
   *
   * @param resource $image
   *   An image resource.
   * @param float $size
   *   The font size.
   * @param float $angle
   *   The angle in degrees.
   * @param int $x
   *   The coordinates given by x and y will define the basepoint of the first
   *   character (roughly the lower-left corner of the character).
   * @param int $y
   *   The y-ordinate. This sets the position of the fonts baseline, not the
   *   very bottom of the character.
   * @param int $color
   *   The color index.
   * @param string $fontfile
   *   The path to the TrueType font to use.
   * @param string $text
   *   The text string in UTF-8 encoding.
   *
   * @return int[]
   *   An array with 8 elements representing four points making the bounding
   *   box of the text.
   *
   * @see http://php.net/manual/en/function.imagettftext.php
   */
  protected function imagettftextWrapper($image, $size, $angle, $x, $y, $color, $fontfile, $text) {
    if (function_exists('imagettftext')) {
      return imagettftext($image, $size, $angle, $x, $y, $color, $fontfile, $text);
    }
    else {
      // @todo \InvalidArgumentException is incorrect, but other exceptions
      // would not be managed by toolkits that implement ImageToolkitBase.
      // Change to \RuntimeException when #2583041 is committed.
      throw new \InvalidArgumentException("The imagettftext() PHP function is not available, and image effects using fonts cannot be executed");
    }
  }

  /**
   * Wrapper of imagettfbbox().
   *
   * If imagettfbbox() is missing, throw an exception instead of failing
   * fatally.
   *
   * @param float $size
   *   The font size.
   * @param float $angle
   *   The angle in degrees.
   * @param string $fontfile
   *   The path to the TrueType font to use.
   * @param string $text
   *   The string to be measured.
   *
   * @return int[]|false
   *   Array with 8 elements representing four points making the bounding box
   *   of the text on success and FALSE on error.
   *
   * @see http://php.net/manual/en/function.imagettfbbox.php
   */
  protected function imagettfbboxWrapper($size, $angle, $fontfile, $text) {
    if (function_exists('imagettfbbox')) {
      return imagettfbbox($size, $angle, $fontfile, $text);
    }
    else {
      // @todo \InvalidArgumentException is incorrect, but other exceptions
      // would not be managed by toolkits that implement ImageToolkitBase.
      // Change to \RuntimeException when #2583041 is committed.
      throw new \InvalidArgumentException("The imagettfbbox() PHP function is not available, and image effects using fonts cannot be executed");
    }
  }

  /**
   * Change overall image transparency level.
   *
   * This method implements the algorithm described in
   * http://php.net/manual/en/function.imagefilter.php#82162
   *
   * @param resource $img
   *   Image resource id.
   * @param int $pct
   *   Opacity of the source image in percentage.
   *
   * @return bool
   *   Returns TRUE on success or FALSE on failure.
   *
   * @see http://php.net/manual/en/function.imagefilter.php#82162
   */
  protected function filterOpacity($img, $pct) {
    if (!isset($pct)) {
      return FALSE;
    }
    $pct /= 100;

    // Get image width and height.
    $w = imagesx($img);
    $h = imagesy($img);

    // Turn alpha blending off.
    imagealphablending($img, FALSE);

    // Find the most opaque pixel in the image (the one with the smallest alpha
    // value).
    $min_alpha = 127;
    for ($x = 0; $x < $w; $x++) {
      for ($y = 0; $y < $h; $y++) {
        $alpha = (imagecolorat($img, $x, $y) >> 24) & 0xFF;
        if ($alpha < $min_alpha) {
          $min_alpha = $alpha;
        }
      }
    }

    // Loop through image pixels and modify alpha for each.
    for ($x = 0; $x < $w; $x++) {
      for ($y = 0; $y < $h; $y++) {
        // Get current alpha value (represents the TANSPARENCY!).
        $color_xy = imagecolorat($img, $x, $y);
        $alpha = ($color_xy >> 24) & 0xFF;
        // Calculate new alpha.
        if ($min_alpha !== 127) {
          $alpha = 127 + 127 * $pct * ($alpha - 127) / (127 - $min_alpha);
        }
        else {
          $alpha += 127 * $pct;
        }
        // Get the color index with new alpha.
        $alpha_color_xy = imagecolorallocatealpha($img, ($color_xy >> 16) & 0xFF, ($color_xy >> 8) & 0xFF, $color_xy & 0xFF, $alpha);
        // Set pixel with the new color + opacity.
        if (!imagesetpixel($img, $x, $y, $alpha_color_xy)) {
          return FALSE;
        }
      }
    }

    return TRUE;
  }

  /**
   * Gets a copy of the source with the Gaussian Blur algorithm applied.
   *
   * This method implements in PHP the algorithm described in
   * https://github.com/libgd/libgd/blob/master/src/gd_filter.c for the
   * gdImageCopyGaussianBlurred function.
   *
   * 'radius' is a radius, not a diameter so a radius of 2 (for example) will
   * blur across a region 5 pixels across (2 to the center, 1 for the center
   * itself and another 2 to the other edge).
   * 'sigma' represents the "fatness" of the curve (lower == fatter). If
   * 'sigma' is NULL, the fucntions ignores it and instead computes an
   * "optimal" value.
   *
   * More details:
   * A Gaussian Blur is generated by replacing each pixel's color values with
   * the average of the surrounding pixels' colors. This region is a circle
   * whose radius is given by argument 'radius'. Thus, a larger radius will
   * yield a blurrier image.
   * This average is not a simple mean of the values. Instead, values are
   * weighted using the Gaussian function (roughly a bell curve centered around
   * the destination pixel) giving it much more influence on the result than
   * its neighbours.  Thus, a fatter curve  will give the center pixel more
   * weight and make the image less blurry; lower 'sigma' values will yield
   * flatter curves.
   * Currently, the default sigma is computed as (2/3)*radius.
   *
   * @param resource $src
   *   The source image resource.
   * @param int $radius
   *   The blur radius (*not* diameter: range is 2*radius + 1).
   * @param float $sigma
   *   (optional) The sigma value or NULL to use the computed default.
   *
   * @return resource
   *   The computed new image resource, or NULL if an error occurred.
   */
  protected function imageCopyGaussianBlurred($src, $radius, $sigma = NULL) {
    // Radius must be a positive integer.
    if ($radius < 1) {
      return NULL;
    }

    // Compute the coefficients.
    $coeffs = GdGaussianBlur::gaussianCoeffs($radius, $sigma);
    if (!$coeffs) {
      return NULL;
    }

    // Get image width and height.
    $w = imagesx($src);
    $h = imagesy($src);

    // Apply the filter horizontally.
    // @todo when #2583041 is committed, add a check for memory
    // availability before creating the resource.
    $tmp = imagecreatetruecolor($w, $h);
    imagealphablending($tmp, FALSE);
    if (!$tmp) {
      return NULL;
    }
    GdGaussianBlur::applyCoeffs($src, $tmp, $coeffs, $radius, 'HORIZONTAL');

    // Apply the filter vertically.
    // @todo when #2583041 is committed, add a check for memory
    // availability before creating the resource.
    $result = imagecreatetruecolor($w, $h);
    imagealphablending($result, FALSE);;
    if ($result) {
      GdGaussianBlur::applyCoeffs($tmp, $result, $coeffs, $radius, 'VERTICAL');
    }

    // Destroy temp resource and return result.
    imagedestroy($tmp);
    return $result;
  }

}
