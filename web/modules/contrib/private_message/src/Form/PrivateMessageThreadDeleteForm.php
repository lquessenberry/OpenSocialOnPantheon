<?php

namespace Drupal\private_message\Form;

use Drupal\Core\Entity\ContentEntityConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\user\Entity\User;

/**
 * Form definition for the private message delete form.
 */
class PrivateMessageThreadDeleteForm extends ContentEntityConfirmFormBase {

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    $members = $this->getEntity()->getMembers();
    $member_names = [];
    foreach ($members as $member) {
      $member_names[] = $member->getDisplayName();
    }

    return $this->t('Are you sure you want to delete the thread between you and the following users: @others?', ['@others' => implode(', ', $member_names)]);
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return Url::fromRoute('entity.private_message_thread.canonical', ['private_message_thread' => $this->getEntity()->id()]);
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return $this->t('Delete thread');
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);

    $user = User::load($this->currentUser()->id());
    $this->getEntity()->delete($user);

    $form_state->setRedirect('private_message.private_message_page');
  }

}
