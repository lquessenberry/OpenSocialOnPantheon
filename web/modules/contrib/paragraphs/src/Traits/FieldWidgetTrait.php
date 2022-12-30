<?php

namespace Drupal\paragraphs\Traits;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * Methods to help Paragraphs field widgets.
 */
trait FieldWidgetTrait {

  /**
   * Initializes form language code values.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param \Drupal\Core\Entity\EntityInterface $host
   *   The host entity.
   *
   * @see ContetEntityForm::initFormLangcodes()
   */
  protected function initFormLangcodes(FormStateInterface $form_state, EntityInterface $host) {
    /** @var \Drupal\Core\Entity\EntityRepositoryInterface $entity_repository */
    $entity_repository = \Drupal::service('entity.repository');

    // Store the entity default language to allow checking whether the form is
    // dealing with the original entity or a translation.
    if (!$form_state->has('entity_default_langcode')) {
      $form_state->set('entity_default_langcode', $host->getUntranslated()->language()->getId());
    }
    // This value might have been explicitly populated to work with a particular
    // entity translation. If not we fall back to the most proper language based
    // on contextual information.
    if (!$form_state->has('langcode')) {
      // Imply a 'view' operation to ensure users edit entities in the same
      // language they are displayed. This allows to keep contextual editing
      // working also for multilingual entities.
      $form_state->set('langcode', $entity_repository->getTranslationFromContext($host)->language()->getId());
    }
  }

}
