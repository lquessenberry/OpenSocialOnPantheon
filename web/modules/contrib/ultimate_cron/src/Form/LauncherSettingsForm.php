<?php

namespace Drupal\ultimate_cron\Form;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Form for launcher settings.
 */
class LauncherSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'ultimate_cron_launcher_settings';
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

    $form['timeouts'] = [
      '#type' => 'fieldset',
      '#title' => t('Timeouts'),
    ];
    $form['launcher'] = [
      '#type' => 'fieldset',
      '#title' => t('Launching options'),
    ];
    $form['timeouts']['lock_timeout'] = [
      '#title' => t('Job lock timeout'),
      '#type' => 'textfield',
      '#default_value' => $values->get('launcher.lock_timeout'),
      '#description' => t('Number of seconds to keep lock on job.'),
      '#fallback' => TRUE,
      '#required' => TRUE,
    ];
    $form['timeouts']['max_execution_time'] = [
      '#title' => t('Maximum execution time'),
      '#type' => 'textfield',
      '#default_value' => $values->get('launcher.max_execution_time'),
      '#description' => t('Maximum execution time for a cron run in seconds.'),
      '#fallback' => TRUE,
      '#required' => TRUE,
    ];
    $form['launcher']['max_threads'] = [
      '#title' => t('Maximum number of launcher threads'),
      '#type' => 'textfield',
      '#default_value' => $values->get('launcher.max_threads'),
      '#description' => t('The maximum number of launch threads that can be running at any given time.'),
      '#fallback' => TRUE,
      '#required' => TRUE,
      '#weight' => 1,
    ];

    $options = ['any', '-- fixed --', '1'];

    $form['launcher']['thread'] = [
      '#title' => t('Run in thread'),
      '#type' => 'select',
      '#default_value' => $values->get('launcher.thread'),
      '#options' => $options,
      '#description' => t('Which thread to run jobs in.') . '<br/>' .
        t('<strong>Any</strong>: Just use any available thread') . '<br/>' .
        t('<strong>Fixed</strong>: Only run in one specific thread. The maximum number of threads is spread across the jobs.') . '<br/>' .
        t('<strong>1-?</strong>: Only run when a specific thread is invoked. This setting only has an effect when cron is run through cron.php with an argument ?thread=N or through Drush with --options=thread=N.'),
      '#fallback' => TRUE,
      '#required' => TRUE,
      '#weight' => 2,
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('ultimate_cron.settings')
      ->set('launcher.lock_timeout', $form_state->getValue('lock_timeout'))
      ->set('launcher.max_execution_time', $form_state->getValue('max_execution_time'))
      ->set('launcher.max_threads', $form_state->getValue('max_threads'))
      ->set('launcher.thread', $form_state->getValue('thread'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
