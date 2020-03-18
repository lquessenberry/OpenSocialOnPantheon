<?php

namespace Drupal\profile\Form;

use Drupal\Core\Entity\EntityDeleteForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a confirmation form for deleting a Profile type entity.
 */
class ProfileTypeDeleteForm extends EntityDeleteForm {

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $num_profiles = $this->entityTypeManager->getStorage('profile')->getQuery()
      ->condition('type', $this->entity->id())
      ->count()
      ->execute();
    if ($num_profiles) {
      $caption = '<p>' . \Drupal::translation()
        ->formatPlural($num_profiles, '%type is used by 1 profile on your site. You can not remove this profile type until you have removed all of the %type profiles.', '%type is used by @count profiles on your site. You may not remove %type until you have removed all of the %type profiles.', ['%type' => $this->entity->label()]) . '</p>';
      $form['#title'] = $this->getQuestion();
      $form['description'] = ['#markup' => $caption];
      return $form;
    }

    return parent::buildForm($form, $form_state);
  }

}
