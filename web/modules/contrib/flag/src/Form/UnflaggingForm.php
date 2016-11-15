<?php

namespace Drupal\flag\Form;

use Drupal\Core\Form\FormStateInterface;

/**
 * Provides the confirm form page for unflagging an entity.
 *
 * @see \Drupal\flag\Plugin\ActionLink\ConfirmForm
 */
class UnflaggingForm extends FlagConfirmFormBase {

  /**
   * The flagging entity.
   *
   * @var \Drupal\flag\FlaggingInterface
   */
  protected $flagging;

  /**
   * The flaggable entity.
   *
   * @var \Drupal\Core\Entity\EntityInterface
   */
  protected $entity;

  /**
   * The flag entity.
   *
   * @var \Drupal\flag\FlagInterface
   */
  protected $flag;

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'unflagging_form';
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->flag->getLinkTypePlugin()->getUnflagQuestion();
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return $this->flag->getUnflagLongText();
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return $this->t('Unflag');
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    \Drupal::service('flag')->unflag($this->flag, $this->entity);
  }

}
