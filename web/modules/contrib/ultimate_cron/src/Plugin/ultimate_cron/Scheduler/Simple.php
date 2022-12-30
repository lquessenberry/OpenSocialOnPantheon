<?php

namespace Drupal\ultimate_cron\Plugin\ultimate_cron\Scheduler;
use Drupal\Core\Form\FormStateInterface;
use Drupal\ultimate_cron\CronRule;
use Drupal\ultimate_cron\Entity\CronJob;

/**
 * Simple scheduler.
 *
 * @SchedulerPlugin(
 *   id = "simple",
 *   title = @Translation("Simple"),
 *   description = @Translation("Provides a set of predefined intervals for scheduling."),
 * )
 */
class Simple extends Crontab {

  public $presets = array(
    '* * * * *' => 60,
    '*/5+@ * * * *' => 300,
    '*/15+@ * * * *' => 900,
    '*/30+@ * * * *' => 1800,
    '0+@ * * * *' => 3600,
    '0+@ */3 * * *' => 10800,
    '0+@ */6 * * *' => 21600,
    '0+@ */12 * * *' => 43200,
    '0+@ 0 * * *' => 86400,
    '0+@ 0 * * 0' => 604800,
  );

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return array(
      'rules' => array('*/15+@ * * * *'),
    ) + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsLabel($name, $value) {
    switch ($name) {
      case 'rules':
        return isset($value[0]) ? \Drupal::service('date.formatter')->formatInterval($this->presets[$value[0]]) : $value;
    }
    return parent::settingsLabel($name, $value);
  }

  /**
   * {@inheritdoc}
   */
  public function formatLabel(CronJob $job) {
    return t('Every @interval', array(
      '@interval' => \Drupal::service('date.formatter')->formatInterval($this->presets[$this->configuration['rules'][0]]),
    ));
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $date_formatter = \Drupal::service('date.formatter');
    $intervals = array_map(array($date_formatter, 'formatInterval'), $this->presets);

    $form['rules'][0] = array(
      '#type' => 'select',
      '#title' => t('Run cron every'),
      '#default_value' => $this->configuration['rules'][0],
      '#description' => t('Select the interval you wish cron to run on.'),
      '#options' => $intervals,
      '#fallback' => TRUE,
      '#required' => TRUE,
    );

    return $form;
  }
}
