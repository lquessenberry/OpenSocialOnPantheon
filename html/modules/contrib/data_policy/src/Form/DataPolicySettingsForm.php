<?php

namespace Drupal\data_policy\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Class DataPolicySettingsForm.
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

    $form['enforce_consent'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enforce consent'),
      '#description' => $this->t('A user should give your consent on data policy when he creates an account.'),
      '#default_value' => $config->get('enforce_consent'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('data_policy.data_policy')
      ->set('enforce_consent', $form_state->getValue('enforce_consent'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
