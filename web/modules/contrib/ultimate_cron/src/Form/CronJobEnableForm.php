<?php

namespace Drupal\ultimate_cron\Form;

use Drupal\Core\Entity\EntityConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;


class CronJobEnableForm extends EntityConfirmFormBase {

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Do you really want to enable @cronjob_id cron job?', array(
      '@cronjob_id' => $this->getEntity()->label(),
    ));
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return $this->t('This cron job will be executed again.');
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return $this->getEntity()->toUrl('collection');
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return $this->t('Enable');
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->entity->enable()->save();
    $this->messenger()
      ->addStatus($this->t('Enabled cron job %cronjob.', ['%cronjob' => $this->entity->label()]));
    $form_state->setRedirectUrl($this->getCancelUrl());
  }
  
}
