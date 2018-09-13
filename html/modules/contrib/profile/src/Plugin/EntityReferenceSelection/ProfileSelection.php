<?php

namespace Drupal\profile\Plugin\EntityReferenceSelection;

use Drupal\Core\Entity\Plugin\EntityReferenceSelection\DefaultSelection;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides specific access control for the profile entity type.
 *
 * @EntityReferenceSelection(
 *   id = "default:profile",
 *   label = @Translation("Profile selection"),
 *   entity_types = {"profile"},
 *   group = "default",
 *   weight = 1
 * )
 */
class ProfileSelection extends DefaultSelection {

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);
    $form['target_bundles']['#title'] = $this->t('Profile types');
    return $form;
  }

}
