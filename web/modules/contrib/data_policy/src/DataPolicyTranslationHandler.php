<?php

namespace Drupal\data_policy;

use Drupal\content_translation\ContentTranslationHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * Defines the translation handler for data_policy.
 */
class DataPolicyTranslationHandler extends ContentTranslationHandler {

  /**
   * {@inheritdoc}
   */
  public function entityFormAlter(array &$form, FormStateInterface $form_state, EntityInterface $entity) {
    parent::entityFormAlter($form, $form_state, $entity);

    if (isset($form['content_translation'])) {
      // We do not need to show these values to simplify UI: they
      // inherit the basic entity property values.
      $form['content_translation']['#access'] = FALSE;
    }
  }

}
