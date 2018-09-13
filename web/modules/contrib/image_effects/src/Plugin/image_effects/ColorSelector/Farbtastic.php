<?php

namespace Drupal\image_effects\Plugin\image_effects\ColorSelector;

use Drupal\image_effects\Plugin\ImageEffectsPluginBase;

/**
 * Farbtastic color selector plugin.
 *
 * @Plugin(
 *   id = "farbtastic",
 *   title = @Translation("Farbtastic color selector"),
 *   short_title = @Translation("Farbtastic"),
 *   help = @Translation("Use a Farbtastic color picker to select colors.")
 * )
 */
class Farbtastic extends ImageEffectsPluginBase {

  /**
   * {@inheritdoc}
   */
  public function selectionElement(array $options = []) {
    return [
      '#type' => 'textfield',
      '#title' => isset($options['#title']) ? $options['#title'] : $this->t('Color'),
      '#description' => isset($options['#description']) ? $options['#description'] : NULL,
      '#default_value' => $options['#default_value'],
      '#field_suffix' => '<div class="farbtastic-colorpicker"></div>',
      '#maxlength' => 7,
      '#size' => 7,
      '#wrapper_attributes' => ['class' => ['image-effects-farbtastic-color-selector']],
      '#attributes' => ['class' => ['image-effects-color-textfield']],
      '#attached' => ['library' => ['image_effects/image_effects.farbtastic_color_selector']],
    ];
  }

}
