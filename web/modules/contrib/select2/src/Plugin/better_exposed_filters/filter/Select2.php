<?php

namespace Drupal\select2\Plugin\better_exposed_filters\filter;

use Drupal\better_exposed_filters\Plugin\better_exposed_filters\filter\FilterWidgetBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Select2 widget implementation.
 *
 * @BetterExposedFiltersFilterWidget(
 *   id = "bef_select2",
 *   label = @Translation("Select2"),
 * )
 */
class Select2 extends FilterWidgetBase {

  /**
   * {@inheritdoc}
   */
  public function exposedFormAlter(array &$form, FormStateInterface $form_state) {

    $field_id = $this->getExposedFilterFieldId();

    parent::exposedFormAlter($form, $form_state);

    if (!empty($form[$field_id])) {
      $filter = $this->handler;

      $form[$field_id]['#type'] = 'select2';
      $form[$field_id]['#autocomplete'] = !empty($filter->options['type']) && $filter->options['type'] === 'textfield';
      $form[$field_id]['#multiple'] = !empty($filter->options['expose']['multiple']) && $filter->options['expose']['multiple'];
      $form[$field_id]['#select2'] = [
        'width' => '100%',
        'allowClear' => FALSE,
      ];
    }
  }

}
