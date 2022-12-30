<?php

namespace Drupal\typed_data\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Annotation class for typed data form widget plugins.
 *
 * @Annotation
 */
class TypedDataFormWidget extends Plugin {

  /**
   * The machine-name of the widget.
   *
   * @var string
   */
  public $id;

  /**
   * The human-readable name of the widget.
   *
   * @var \Drupal\Core\Annotation\Translation
   *
   * @ingroup plugin_translatable
   */
  public $label;

  /**
   * The human-readable description of the widget.
   *
   * @var \Drupal\Core\Annotation\Translation
   *
   * @ingroup plugin_translatable
   */
  public $description;

}
