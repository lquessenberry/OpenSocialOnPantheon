<?php

namespace Drupal\image_effects\Plugin\ImageToolkit\Operation;

use Drupal\Core\StreamWrapper\LocalStream;

/**
 * Base trait for image toolkit operations that require font handling.
 */
trait FontOperationTrait {

  /**
   * The stream wrapper manager service.
   *
   * @var \Drupal\Core\StreamWrapper\StreamWrapperManagerInterface
   */
  protected $streamWrapperManagerForFontHandling;

  /**
   * An array of resolved font file URIs.
   *
   * @var array
   */
  protected static $fontPaths = [];

  /**
   * Return the real path of the specified file.
   *
   * @param string $uri
   *   An URI.
   *
   * @return string
   *   The local path of the file.
   */
  protected function getRealFontPath($uri) {
    $uri_wrapper = $this->getStreamWrapperManagerForFontHandling()->getViaUri($uri);
    if ($uri_wrapper instanceof LocalStream) {
      return $uri_wrapper->realpath();
    }
    else {
      return is_file($uri) ? $uri : NULL;
    }
  }

  /**
   * Return the path of the font file.
   *
   * @param string $font_uri
   *   The font URI.
   *
   * @return string
   *   The local path of the font file.
   */
  protected function getFontPath($font_uri) {
    if (!$font_uri) {
      throw new \InvalidArgumentException('Font file not specified');
    }
    if (!isset(static::$fontPaths[$font_uri])) {
      if (!$ret = $this->getRealFontPath($font_uri)) {
        throw new \InvalidArgumentException("Could not find the font file {$font_uri}");
      }
      static::$fontPaths[$font_uri] = $ret;
    }
    return static::$fontPaths[$font_uri];
  }

  /**
   * Returns the stream wrapper manager service.
   *
   * @return \Drupal\Core\StreamWrapper\streamWrapperManagerInterface
   *   The stream wrapper manager service.
   */
  protected function getStreamWrapperManagerForFontHandling() {
    if (!$this->streamWrapperManagerForFontHandling) {
      $this->streamWrapperManagerForFontHandling = \Drupal::service('stream_wrapper_manager');
    }
    return $this->streamWrapperManagerForFontHandling;
  }

}
