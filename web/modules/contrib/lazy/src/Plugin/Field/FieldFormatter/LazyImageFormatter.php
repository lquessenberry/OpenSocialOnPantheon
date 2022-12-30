<?php

namespace Drupal\lazy\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\image\Plugin\Field\FieldFormatter\ImageFormatter;

/**
 * Plugin implementation of the 'lazy_image' formatter.
 *
 * @FieldFormatter(
 *   id = "lazy_image",
 *   label = @Translation("Image (Lazy-load)"),
 *   field_types = {
 *     "image"
 *   },
 *   quickedit = {
 *     "editor" = "image"
 *   }
 * )
 */
class LazyImageFormatter extends ImageFormatter {

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = parent::viewElements($items, $langcode);

    foreach ($elements as $delta => $element) {
      $elements[$delta]['#item_attributes']['data-lazy'] = TRUE;
    }

    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies() {
    $dependencies = parent::calculateDependencies();
    $dependencies['module'][] = 'image';
    $dependencies['config'][] = 'lazy.settings';
    return $dependencies;
  }

}
