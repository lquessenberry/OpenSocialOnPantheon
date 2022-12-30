<?php

namespace Drupal\image_effects\Plugin\ImageToolkit\Operation\imagemagick;

use Drupal\imagemagick\Plugin\ImageToolkit\Operation\imagemagick\ImagemagickImageToolkitOperationBase;
use Drupal\image_effects\Component\Rectangle;
use Drupal\image_effects\Plugin\ImageToolkit\Operation\RotateTrait;

/**
 * Defines ImageMagick Rotate operation.
 *
 * @ImageToolkitOperation(
 *   id = "image_effects_imagemagick_rotate",
 *   toolkit = "imagemagick",
 *   operation = "rotate_ie",
 *   label = @Translation("Rotate"),
 *   description = @Translation("Rotate image.")
 * )
 */
class Rotate extends ImagemagickImageToolkitOperationBase {

  use RotateTrait;
  use ImagemagickOperationTrait;

  /**
   * {@inheritdoc}
   */
  protected function execute(array $arguments) {
    $toolkit_arguments = $this->getToolkit()->arguments();

    if ($this->getToolkit()->getExecManager()->getPackage() === 'graphicsmagick' && !empty($arguments['background'])) {
      // GraphicsMagick does not support alpha in background color.
      $arguments['background'] = substr($arguments['background'], 0, 7);
    }

    if (empty($arguments['background'])) {
      $format = $toolkit_arguments->getDestinationFormat() ?: $toolkit_arguments->getSourceFormat();
      $mime_type = $this->getFormatMapper()->getMimeTypeFromFormat($format);
      if ($mime_type === 'image/jpeg') {
        // JPEG does not allow transparency. Set to fallback color.
        $this->addArgument('-background ' . $this->escapeArgument($arguments['fallback_transparency_color']));
      }
      else {
        $this->addArgument('-background transparent');
      }
    }
    else {
      $this->addArgument('-background ' . $this->escapeArgument($arguments['background']));
    }

    // Rotate.
    $this->addArgument('-rotate ' . $arguments['degrees']);
    $this->addArgument('+repage');

    // Need to resize the image after rotation to make sure it complies with
    // the dimensions expected, calculated via the Rectangle class.
    if ($this->getToolkit()->getWidth() && $this->getToolkit()->getHeight()) {
      $box = new Rectangle($this->getToolkit()->getWidth(), $this->getToolkit()->getHeight());
      $box = $box->rotate((float) $arguments['degrees']);
      return $this->getToolkit()->apply('resize', [
        'width' => $box->getBoundingWidth(),
        'height' => $box->getBoundingHeight(),
        'filter' => $arguments['resize_filter'],
      ]);
    }

    return TRUE;
  }

}
