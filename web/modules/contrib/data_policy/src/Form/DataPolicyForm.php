<?php

namespace Drupal\data_policy\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageInterface;

/**
 * Default form controller for Data policy.
 *
 * @ingroup data_policy
 */
class DataPolicyForm extends ContentEntityForm {

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);

    if ($this->clearMessage()) {
      $form['revision_log_message']['widget'][0]['value']['#default_value'] = '';
    }

    $entity_id = $this->config('data_policy.data_policy')->get('entity_id');
    $is_new = empty($entity_id);

    $form['active_revision'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Active'),
      '#description' => $this->t('When this field is checked, after submitting the form, a new revision will be created which will be marked active.'),
      '#default_value' => $is_new,
      '#disabled' => $is_new,
      '#weight' => 10,
    ];

    $form['new_revision'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Create new revision'),
      '#default_value' => TRUE,
      '#disabled' => TRUE,
      '#weight' => 10,
    ];

    if (isset($form['langcode'])) {
      $form['langcode']['widget'][0]['value']['#languages'] = LanguageInterface::STATE_CONFIGURABLE;
    }

    return $form;
  }

  /**
   * Get status of clearing revision log message.
   *
   * @return bool
   *   TRUE if the message should be cleared.
   */
  public function clearMessage() {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $active_revision = !empty($form_state->getValue('active_revision'));
    $new_revision = !empty($form_state->getValue('new_revision'));

    if ($active_revision) {
      $this->entity->isDefaultRevision(TRUE);
    }
    else {
      $this->entity->isDefaultRevision(FALSE);
    }

    if ($new_revision) {
      $this->entity->setNewRevision(TRUE);

      $this->entity->setRevisionCreationTime($this->time->getRequestTime());
      $this->entity->setRevisionUserId($this->currentUser()->id());

      $this->messenger()->addStatus($this->t('Created new revision.'));
    }

    return parent::save($form, $form_state);
  }

}
