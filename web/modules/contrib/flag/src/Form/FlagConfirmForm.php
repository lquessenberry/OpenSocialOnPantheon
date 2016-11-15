<?php

namespace Drupal\flag\Form;

use Drupal\flag\Form\FlagConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides the confirm form page for flagging an entity.
 *
 * @see \Drupal\flag\Plugin\ActionLink\ConfirmForm
 */
class FlagConfirmForm extends FlagConfirmFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'flag_confirm_form';
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->flag->getLinkTypePlugin()->getFlagQuestion();
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return $this->flag->getFlagLongText();
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return $this->t('Flag');
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    \Drupal::service('flag')->flag($this->flag, $this->entity);
  }

}
