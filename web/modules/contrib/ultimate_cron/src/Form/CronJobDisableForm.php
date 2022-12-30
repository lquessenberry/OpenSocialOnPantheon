<?php

namespace Drupal\ultimate_cron\Form;

use Drupal\Core\Entity\EntityConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;


class CronJobDisableForm extends EntityConfirmFormBase {

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Do you really want to disable @cronjob_id cron job?', array(
      '@cronjob_id' => $this->getEntity()->label(),
    ));
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return $this->t('This cron job will no longer be executed.');
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
    return $this->t('Disable');
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->entity->disable()->save();
    $this->messenger()
      ->addStatus($this->t('Disabled cron job %cronjob.', ['%cronjob' => $this->entity->label()]));
    $form_state->setRedirectUrl($this->getCancelUrl());
  }
  
}
