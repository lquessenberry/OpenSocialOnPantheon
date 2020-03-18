<?php

namespace Drupal\image_effects\Plugin\ImageToolkit\Operation\imagemagick;

use Drupal\image_effects\Plugin\ImageToolkit\Operation\SetGifTransparentColorTrait;
use Drupal\imagemagick\Plugin\ImageToolkit\Operation\imagemagick\ImagemagickImageToolkitOperationBase;

/**
 * Defines ImageMagick set_gif_transparent_color image operation.
 *
 * @ImageToolkitOperation(
 *   id = "image_effects_imagemagick_set_gif_transparent_color",
 *   toolkit = "imagemagick",
 *   operation = "set_gif_transparent_color",
 *   label = @Translation("Set the image transparent color"),
 *   description = @Translation("Set the image transparent color for GIF images.")
 * )
 */
class SetGifTransparentColor extends ImagemagickImageToolkitOperationBase {

  use ImagemagickOperationTrait;
  use SetGifTransparentColorTrait;

  /**
   * {@inheritdoc}
   */
  protected function execute(array $arguments) {
    $format = $this->getToolkit()->getDestinationFormat() ?: $this->getToolkit()->getSourceFormat();
    $mime_type = $this->getFormatMapper()->getMimeTypeFromFormat($format);
    if ($mime_type === 'image/gif' && $arguments['transparent_color']) {
      $index = $this->getToolkit()->findArgument('-alpha off -transparent-color');
      if ($index !== FALSE) {
        $this->getToolkit()->removeArgument($index);
      }
      $this->getToolkit()->addArgument('-alpha off -transparent-color ' . $this->getToolkit()->escapeShellArg($arguments['transparent_color']) . ' -transparent ' . $this->getToolkit()->escapeShellArg($arguments['transparent_color']));
    }
  }

}
