<?php

namespace Drupal\group\Entity\Views;

use Drupal\views\EntityViewsData;

/**
 * Provides the views data for the group entity type.
 */
class GroupViewsData extends EntityViewsData {

  /**
   * {@inheritdoc}
   */
  public function getViewsData() {
    $data = parent::getViewsData();

    $data['groups_field_data']['id']['argument'] = [
      'id' => 'group_id',
      'name field' => 'label',
      'numeric' => TRUE,
    ];

    return $data;
  }

}
