<?php

namespace Drupal\typed_data\Widget;

use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\typed_data\Annotation\TypedDataFormWidget;

/**
 * Plugin manager for form widgets.
 */
class FormWidgetManager extends DefaultPluginManager implements FormWidgetManagerInterface {

  /**
   * {@inheritdoc}
   */
  public function __construct(\Traversable $namespaces, ModuleHandlerInterface $module_handler, $plugin_definition_annotation_name = TypedDataFormWidget::class) {
    $this->alterInfo('typed_data_form_widget');
    parent::__construct('Plugin/TypedDataFormWidget', $namespaces, $module_handler, FormWidgetInterface::class, $plugin_definition_annotation_name);
  }

}
