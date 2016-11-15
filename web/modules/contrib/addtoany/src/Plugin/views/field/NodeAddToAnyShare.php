<?php

namespace Drupal\addtoany\Plugin\views\field;

use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;

/**
* Field handler to flag the node type.
*
* @ingroup views_field_handlers
*
* @ViewsField("node_addtoany_share")
*/
class NodeAddToAnyShare extends FieldPluginBase {

  /**
   * @{inheritdoc}
   */
  public function query() {
    // Leave empty to avoid a query on this field.
  }

  /**
   * @{inheritdoc}
   */
  public function render(ResultRow $values) {
    $entity = $values->_entity;
    if ($entity->access('view')) {
      return array(
        '#theme' => 'addtoany_standard',
        '#addtoany_html' => addtoany_create_node_buttons($entity),
      );
    }
  }
}
