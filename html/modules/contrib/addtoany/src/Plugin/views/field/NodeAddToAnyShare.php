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
      $data = addtoany_create_entity_data($entity);
      return [
        '#addtoany_html'              => \Drupal::token()->replace($data['addtoany_html'], ['node' => $entity]),
        '#link_url'                   => $data['link_url'],
        '#link_title'                 => $data['link_title'],
        '#button_setting'             => $data['button_setting'],
        '#button_image'               => $data['button_image'],
        '#universal_button_placement' => $data['universal_button_placement'],
        '#buttons_size'               => $data['buttons_size'],
        '#theme'                      => 'addtoany_standard',
      ];
    }
  }

}
