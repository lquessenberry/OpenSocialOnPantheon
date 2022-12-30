<?php

namespace Drupal\ultimate_cron\Plugin\ultimate_cron\Scheduler;

use Drupal\Core\Form\FormStateInterface;
use Drupal\ultimate_cron\CronJobInterface;
use Drupal\ultimate_cron\CronRule;
use Drupal\ultimate_cron\Entity\CronJob;

/**
 * Crontab scheduler.
 *
 * @SchedulerPlugin(
 *   id = "crontab",
 *   title = @Translation("Crontab"),
 *   description = @Translation("Use crontab rules for scheduling jobs."),
 * )
 */
class Crontab extends SchedulerBase {
  /**
   * Default settings.
   * @todo: $catch_up is randomly failing when value is low in some situation. 0 value is ignoring catch_up checks.
   */
  public function defaultConfiguration() {
    return array(
      'rules' => array('0+@ */3 * * *'),
      'catch_up' => '0',
    );
  }

  /**
   * {@inheritdoc}
   */
  public function formatLabel(CronJob $job) {
    return implode("\n", $this->configuration['rules']);
  }

  /**
   * {@inheritdoc}
   */
  public function formatLabelVerbose(CronJob $job) {
    $parsed = '';
    $next_schedule = NULL;
    $time = \Drupal::time()->getRequestTime();
    $skew = $this->getSkew($job);
    foreach ($this->configuration['rules'] as $rule) {
      $cron = CronRule::factory($rule, $time, $skew);
      $parsed .= $cron->parseRule() . "\n";
      $result = $cron->getNextSchedule();
      $next_schedule = is_null($next_schedule) || $next_schedule > $result ? $result : $next_schedule;
      $result = $cron->getLastSchedule();
      if ($time < $result + $this->configuration['catch_up']) {
        $result = floor($time / 60) * 60 + 60;
        $next_schedule = $next_schedule > $result ? $result : $next_schedule;
      }
    }
    $parsed .= t('Next scheduled run at @datetime', array(
      '@datetime' => \Drupal::service('date.formatter')->format($next_schedule, 'custom', 'Y-m-d H:i:s')
    ));
    return $parsed;
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form['rules'][0] = array(
      '#title' => t("Rules"),
      '#type' => 'textfield',
      '#default_value' =>  $this->configuration['rules'],
      '#description' => t('Comma separated list of crontab rules.'),
      '#fallback' => TRUE,
      '#required' => TRUE,
      // @todo: check this out.
      //'#element_validate' => array('ultimate_cron_plugin_crontab_element_validate_rule'),
    );

    $form['rules_help'] = array(
      '#type' => 'fieldset',
      '#title' => t('Rules help'),
      '#collapsible' => TRUE,
      '#collapsed' => TRUE,
    );

    $form['rules_help']['info'] = array(
      '#markup' => file_get_contents(\Drupal::service('extension.list.module')->getPath('ultimate_cron') . '/help/rules.html'),
    );

    $form['catch_up'] = array(
      '#title' => t("Catch up"),
      '#type' => 'textfield',
      '#default_value' => $this->configuration['catch_up'],
      '#description' => t("Don't run job after X seconds of rule."),
      '#fallback' => TRUE,
      '#required' => TRUE,
    );

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::validateConfigurationForm($form, $form_state);
    $rule = $form_state->getValues()['scheduler']['configuration']['rules'][0];
    $cron = CronRule::factory($rule);
    if (!$cron->isValid()) {
      $form_state->setErrorByName('scheduler][configuration][rules][0', t('Rule is invalid'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function settingsFormSubmit(&$form, &$form_state, CronJob $job = NULL) {
    $values = & $form_state['values']['settings'][$this->type][$this->name];

    if (!empty($values['rules'])) {
      $rules = explode(',', $values['rules']);
      $values['rules'] = array_map('trim', $rules);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function isScheduled(CronJob $job) {
    $log_entry = isset($job->log_entry) ? $job->log_entry : $job->loadLatestLogEntry();
    $skew = $this->getSkew($job);
    $class = get_class($this);
    return $class::shouldRun($this->configuration['rules'], $log_entry->start_time, NULL, $this->configuration['catch_up'], $skew) ? TRUE : FALSE;
  }

  /**
   * {@inheritdoc}
   */
  static public function shouldRun($rules, $job_last_ran, $time = NULL, $catch_up = 0, $skew = 0) {
    $time = is_null($time) ? time() : $time;
    foreach ($rules as $rule) {
      $cron = CronRule::factory($rule, $time, $skew);
      $cron_last_ran = $cron->getLastSchedule();

      // @todo: Right now second test is failing randomly on low $catch_up value.
      if ($job_last_ran < $cron_last_ran && $cron_last_ran <= $time) {
        if ($time <= $cron_last_ran + $catch_up || $catch_up == 0) {
          return $time - $job_last_ran;
        }
      }
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function isBehind(CronJob $job) {
    // Disabled jobs are not behind!
    if (!$job->status()) {
      return FALSE;
    }

    $log_entry = isset($job->log_entry) ? $job->log_entry : $job->loadLatestLogEntry();
    // If job hasn't run yet, then who are we to say it's behind its schedule?
    // Check the registered time, and use that if it's available.
    $job_last_ran = $log_entry->start_time;
    if (!$job_last_ran) {
      $registered = \Drupal::config('ultimate_cron')->get('ultimate_cron_hooks_registered');
      if (empty($registered[$job->id()])) {
        return FALSE;
      }
      $job_last_ran = $registered[$job->id()];
    }

    $skew = $this->getSkew($job);
    $next_schedule = NULL;
    foreach ($this->configuration['rules'] as $rule) {
      $cron = CronRule::factory($rule, $job_last_ran, $skew);
      $time = $cron->getNextSchedule();
      $next_schedule = is_null($next_schedule) || $time < $next_schedule ? $time : $next_schedule;
    }
    $behind = \Drupal::time()->getRequestTime() - $next_schedule;

    return $behind > $this->configuration['catch_up'] ? $behind : FALSE;
  }

  /**
   * Get a "unique" skew for a job.
   */
  protected function getSkew(CronJob $job) {
    return $job->getUniqueID() & 0xff;
  }
}
