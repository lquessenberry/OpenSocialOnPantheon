<?php

namespace Drupal\profile\Plugin\views\field;

use Drupal\system\Plugin\views\field\BulkForm;

/**
 * Defines a profile operations bulk form element.
 *
 * @ViewsField("profile_bulk_form")
 */
class ProfileBulkForm extends BulkForm {

  /**
   * {@inheritdoc}
   */
  protected function emptySelectedMessage() {
    return $this->t('No profile selected.');
  }

}
