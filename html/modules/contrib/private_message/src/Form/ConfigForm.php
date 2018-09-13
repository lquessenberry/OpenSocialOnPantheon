<?php

namespace Drupal\private_message\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Defines the configuration form for the private message module.
 */
class ConfigForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'private_message_config_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'private_message.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('private_message.settings');

    $form['enable_email_notifications'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable email notifications'),
      '#default_value' => $config->get('enable_email_notifications'),
    ];

    $form['send_by_default'] = [
      '#type' => 'radios',
      '#title' => $this->t('Default action'),
      '#options' => [
        $this->t('Do not send mails (users can opt-in)'),
        $this->t('Send mails (users can opt-out)'),
      ],
      '#default_value' => (int) $config->get('send_by_default'),
      '#states' => [
        'visible' => [
          ':input[name="enable_email_notifications"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['hide_form_filter_tips'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Hide filter tips'),
      '#description' => $this->t('If this box is checked, the text formats description on the private message form will be removed'),
      '#default_value' => (int) $config->get('hide_form_filter_tips'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('private_message.settings')
      ->set('enable_email_notifications', (bool) $form_state->getValue('enable_email_notifications'))
      ->set('send_by_default', (bool) $form_state->getValue('send_by_default'))
      ->set('hide_form_filter_tips', (bool) $form_state->getValue('hide_form_filter_tips'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
