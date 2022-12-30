<?php

namespace Drupal\typed_data_widget_test;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\typed_data\Widget\FormWidgetManagerTrait;

/**
 * Demonstrates the programmatic use of TypedDataFormWidgets.
 */
class FormWidgetExampleController extends ControllerBase {

  use FormWidgetManagerTrait;

  /**
   * Returns a couple of links to widgets.
   */
  public function listWidgetExamples() {
    $build = [
      '#theme' => 'item_list',
      '#title' => 'Widgets',
      '#items' => [],
    ];

    foreach ($this->getFormWidgetManager()->getDefinitions() as $id => $definition) {
      $build['#items'][$id] = [
        '#title' => $definition['label'],
        '#type' => 'link',
        '#url' => Url::fromRoute('typed_data_widget_test.examples.form', ['widget_id' => $id]),
      ];
    }
    return $build;
  }

}
