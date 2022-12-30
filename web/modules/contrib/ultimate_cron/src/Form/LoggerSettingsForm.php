<?php

namespace Drupal\ultimate_cron\Form;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Form for logger settings.
 */
class LoggerSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'ultimate_cron_logger_settings';
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
    $config = $this->config('ultimate_cron.settings');

    // Setup vertical tabs.
    $form['settings_tabs'] = [
      '#type' => 'vertical_tabs',
    ];

    // Settings for Cache logger.
    $form['cache'] = [
      '#type' => 'details',
      '#title' => t('Cache'),
      '#group' => 'settings_tabs',
      '#tree' => TRUE,
    ];

    $form['cache']['bin'] = array(
      '#type' => 'textfield',
      '#title' => t('Cache bin'),
      '#description' => t('Select which cache bin to use for storing logs.'),
      '#default_value' => $config->get('logger.cache.bin'),
      '#fallback' => TRUE,
      '#required' => TRUE,
    );
    $form['cache']['timeout'] = array(
      '#type' => 'textfield',
      '#title' => t('Cache timeout'),
      '#description' => t('Seconds before cache entry expires (0 = never, -1 = on next general cache wipe).'),
      '#default_value' => $config->get('logger.cache.timeout'),
      '#fallback' => TRUE,
      '#required' => TRUE,
    );

    // Settings for Database logger.
    $form['database'] = [
      '#type' => 'details',
      '#title' => t('Database'),
      '#group' => 'settings_tabs',
      '#tree' => TRUE,
    ];
    $options['method'] = [
      1 => t('Disabled'),
      2 => t('Remove logs older than a specified age'),
      3 => t('Retain only a specific amount of log entries'),
    ];
    $form['database']['method'] = array(
      '#type' => 'select',
      '#title' => t('Log entry cleanup method'),
      '#description' => t('Select which method to use for cleaning up logs.'),
      '#options' => $options['method'],
      '#default_value' => $config->get('logger.database.method'),
      '#fallback' => TRUE,
      '#required' => TRUE,
    );

    $states = array('expire' => array(), 'retain' => array());
    $form['database']['method_expire'] = array(
      '#type' => 'fieldset',
      '#title' => t('Remove logs older than a specified age'),
    ) + $states['expire'];
    $form['database']['method_expire']['expire'] = array(
      '#type' => 'textfield',
      '#title' => t('Log entry expiration'),
      '#description' => t('Remove log entries older than X seconds.'),
      '#default_value' => $config->get('logger.database.method_expire.expire'),
      '#fallback' => TRUE,
      '#required' => TRUE,
    ) + $states['expire'];

    $form['database']['method_retain'] = array(
      '#type' => 'fieldset',
      '#title' => t('Retain only a specific amount of log entries'),
    ) + $states['retain'];
    $form['database']['method_retain']['retain'] = array(
      '#type' => 'textfield',
      '#title' => t('Retain logs'),
      '#description' => t('Retain X amount of log entries.'),
      '#default_value' => $config->get('logger.database.method_retain.retain'),
      '#fallback' => TRUE,
      '#required' => TRUE,
    ) + $states['retain'];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('ultimate_cron.settings')
      ->set('logger.cache', $form_state->getValue('cache'))
      ->set('logger.database', $form_state->getValue('database'))
      ->save('');

    parent::submitForm($form, $form_state);
  }

}
