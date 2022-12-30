<?php

namespace Drupal\ultimate_cron\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Form for scheduler settings.
 */
class SchedulerSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'ultimate_cron_scheduler_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['ultimate_cron.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $values = $this->config('ultimate_cron.settings');
    $rules = is_array($values->get('rules')) ? implode(';', $values->get('rules')) : '';

    // Setup vertical tabs.
    $form['settings_tabs'] = array(
      '#type' => 'vertical_tabs',
    );

    // Settings for crontab.
    $form['crontab'] = [
      '#type' => 'details',
      '#title' => 'Crontab',
      '#group' => 'settings_tabs',
      '#tree' => TRUE,
    ];

    $form['crontab']['catch_up'] = array(
      '#title' => t("Catch up"),
      '#type' => 'textfield',
      '#default_value' => $values->get('catch_up'),
      '#description' => t("Don't run job after X seconds of rule."),
      '#fallback' => TRUE,
      '#required' => TRUE,
    );

    $form['crontab']['rules'] = array(
      '#title' => t("Rules"),
      '#type' => 'textfield',
      '#default_value' => $rules,
      '#description' => t('Semi-colon separated list of crontab rules.'),
      '#fallback' => TRUE,
      '#required' => TRUE,
      '#element_validate' => array('ultimate_cron_plugin_crontab_element_validate_rule'),
    );
    $form['crontab']['rules_help'] = array(
      '#type' => 'fieldset',
      '#title' => t('Rules help'),
      '#collapsible' => TRUE,
      '#collapsed' => TRUE,
    );
    $form['crontab']['rules_help']['info'] = array(
      '#markup' => file_get_contents(\Drupal::service('extension.list.module')->getPath('ultimate_cron') . '/help/rules.html'),
    );

    // Settings for Simple scheduler.
    $form['simple'] = [
      '#type' => 'details',
      '#title' => t('Simple'),
      '#group' => 'settings_tabs',
      '#tree' => TRUE,
    ];

    $options = [
      '* * * * *' => 'Every minute',
      '*/15+@ * * * *' => 'Every 15 minutes',
      '*/30+@ * * * *' => 'Every 30 minutes',
      '0+@ * * * *' => 'Every hour',
      '0+@ */3 * * *' => 'Every 3 hours',
      '0+@ */6 * * *' => 'Every 6 hours',
      '0+@ */12 * * *' => 'Every 12 hours',
      '0+@ 0 * * *' => 'Every day',
      '0+@ 0 * * 0' => 'Every week',
    ];
    $form['simple']['rule'] = array(
      '#type' => 'select',
      '#title' => t('Run cron every'),
      '#default_value' => $values->get('rule'),
      '#description' => t('Select the interval you wish cron to run on.'),
      '#options' => $options,
      '#fallback' => TRUE,
      '#required' => TRUE,
    );

    parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('ultimate_cron.settings')
      ->set('scheduler.crontab', $form_state->getValue('crontab'))
      ->set('scheduler.simple', explode(';', $form_state->getValue('simple')))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
