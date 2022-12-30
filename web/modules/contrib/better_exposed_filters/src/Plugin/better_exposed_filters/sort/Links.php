<?php

namespace Drupal\better_exposed_filters\Plugin\better_exposed_filters\sort;

use Drupal\Core\Form\FormStateInterface;

/**
 * Radio Buttons sort widget implementation.
 *
 * @BetterExposedFiltersSortWidget(
 *   id = "bef_links",
 *   label = @Translation("Links"),
 * )
 */
class Links extends SortWidgetBase {

  /**
   * {@inheritdoc}
   */
  public function exposedFormAlter(array &$form, FormStateInterface $form_state) {
    parent::exposedFormAlter($form, $form_state);

    foreach ($this->sortElements as $element) {
      if (!empty($form[$element])) {
        $form[$element]['#theme'] = 'bef_links';

        // Exposed form displayed as blocks can appear on pages other than
        // the view results appear on. This can cause problems with
        // select_as_links options as they will use the wrong path. We
        // provide a hint for theme functions to correct this.
        $form[$element]['#bef_path'] = $this->getExposedFormActionUrl($form_state);
      }
    }
  }

}
