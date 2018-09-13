<?php

namespace Drupal\image_effects\Plugin\ImageToolkit\Operation\imagemagick;

use Drupal\image_effects\Plugin\ImageToolkit\Operation\TextToWrapperTrait;
use Drupal\imagemagick\Plugin\ImageToolkit\Operation\imagemagick\ImagemagickImageToolkitOperationBase;

/**
 * Defines Imagemagick Text Overlay text-to-wrapper operation.
 *
 * @ImageToolkitOperation(
 *   id = "image_effects_imagemagick_text_to_wrapper",
 *   toolkit = "imagemagick",
 *   operation = "text_to_wrapper",
 *   label = @Translation("Overlays text over an image"),
 *   description = @Translation("Overlays text over an image.")
 * )
 */
class TextToWrapper extends ImagemagickImageToolkitOperationBase {

  use TextToWrapperTrait;

  /**
   * {@inheritdoc}
   */
  protected function execute(array $arguments) {
    // Get a temporary wrapper image object via the GD toolkit.
    $gd_wrapper = \Drupal::service('image.factory')->get(NULL, 'gd');
    $gd_wrapper->apply('text_to_wrapper', $arguments);
    // Flush the temporary wrapper to disk, reopen via ImageMagick and return.
    if ($gd_wrapper) {
      $tmp_file = \Drupal::service('file_system')->tempnam('temporary://', 'image_effects_');
      $gd_wrapper_destination = $tmp_file . '.png';
      file_unmanaged_move($tmp_file, $gd_wrapper_destination, FILE_CREATE_DIRECTORY);
      $gd_wrapper->save($gd_wrapper_destination);
      $tmp_wrapper = \Drupal::service('image.factory')->get($gd_wrapper_destination, 'imagemagick');
      return $this->getToolkit()->apply('replace_image', ['replacement_image' => $tmp_wrapper]);
    }
    return FALSE;
  }

}
