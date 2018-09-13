<?php

namespace Drupal\profile;

use Drupal\views\EntityViewsData;

/**
 * Provides the views data for the node entity type.
 */
class ProfileViewsData extends EntityViewsData {

  /**
   * {@inheritdoc}
   */
  public function getViewsData() {
    $data = parent::getViewsData();
    $data['profile']['type']['title'] = t('Profile type');
    $data['profile']['profile_bulk_form'] = [
      'title' => $this->t('Profile operations bulk form'),
      'help' => $this->t('Add a form element that lets you run operations on multiple profiles.'),
      'field' => [
        'id' => 'profile_bulk_form',
      ],
    ];

    return $data;
  }

}
