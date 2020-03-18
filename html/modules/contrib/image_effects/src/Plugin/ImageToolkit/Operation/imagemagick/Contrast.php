<?php

namespace Drupal\image_effects\Plugin\ImageToolkit\Operation\imagemagick;

use Drupal\image_effects\Plugin\ImageToolkit\Operation\ContrastTrait;
use Drupal\imagemagick\Plugin\ImageToolkit\Operation\imagemagick\ImagemagickImageToolkitOperationBase;

/**
 * Defines ImageMagick Contrast operation.
 *
 * @ImageToolkitOperation(
 *   id = "image_effects_imagemagick_contrast",
 *   toolkit = "imagemagick",
 *   operation = "contrast",
 *   label = @Translation("Contrast"),
 *   description = @Translation("Adjust image contrast.")
 * )
 */
class Contrast extends ImagemagickImageToolkitOperationBase {

  use ContrastTrait;

  /**
   * {@inheritdoc}
   */
  protected function execute(array $arguments) {
    if ($arguments['level']) {
      $this->getToolkit()->addArgument('-brightness-contrast ' . $this->getToolkit()->escapeShellArg('0x' . $arguments['level']));
    }

    return TRUE;
  }

}
