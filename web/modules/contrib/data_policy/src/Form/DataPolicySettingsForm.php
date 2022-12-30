<?php

namespace Drupal\data_policy\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Settings form.
 *
 * @ingroup data_policy
 */
class DataPolicySettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'data_policy_data_policy_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['data_policy.data_policy'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('data_policy.data_policy');

    $form['consent_text'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Consent text'),
      '#default_value' => $config->get('consent_text'),
      '#description' => $this->t('Each line is a separate checkbox. Accordingly, in order to have several checkboxes, you need to add several lines. To insert an entity into a string, you need to use tokens according to a special template: [id:1] where 1 is the entity ID from the page "/admin/config/people/data-policy", if the checkbox must be required, then after the entity ID you need to insert the "*" character.'),
      '#rows' => 5,
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('data_policy.data_policy')
      ->set('consent_text', $form_state->getValue('consent_text'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
