<?php

namespace Drupal\ultimate_cron;

class CronSignal {
  /**
   * Get a signal without claiming it.
   *
   * @param string $name
   *   The name of the job.
   * @param string $signal
   *   The name of the signal.
   *
   * @return string
   *   The signal if any.
   */
  static public function peek($name, $signal) {
    $database = \Drupal::service('ultimate_cron.database_factory');
    return $database->select('ultimate_cron_signal', 's')
      ->fields('s', array('job_name'))
      ->condition('job_name', $name)
      ->condition('signal_name', $signal)
      ->condition('claimed', 0)
      ->execute()
      ->fetchField();
  }

  /**
   * Get and claim signal.
   *
   * @param string $name
   *   The name of the job.
   * @param string $signal
   *   The name of the signal.
   *
   * @return string
   *   The signal if any. If a signal is found, it is "claimed" and therefore
   *   cannot be claimed again.
   */
  static public function get($name, $signal) {
    $database = \Drupal::service('ultimate_cron.database_factory');
    $claimed = $database->update('ultimate_cron_signal')
      ->fields(array('claimed' => 1))
      ->condition('job_name', $name)
      ->condition('signal_name', $signal)
      ->condition('claimed', 0)
      ->execute();
    if ($claimed) {
      $database->delete('ultimate_cron_signal')
        ->condition('job_name', $name)
        ->condition('signal_name', $signal)
        ->condition('claimed', 1)
        ->execute();
    }
    return $claimed;
  }

  /**
   * Set signal.
   *
   * @param string $name
   *   The name of the job.
   * @param string $signal
   *   The name of the signal.
   *
   * @return boolean
   *   TRUE if the signal was set.
   * @throws \Exception
   */
  static public function set($name, $signal) {
    $database = \Drupal::service('ultimate_cron.database_factory');
    return $database->merge('ultimate_cron_signal')
      ->keys(array(
        'job_name' => $name,
        'signal_name' => $signal,
      ))
      ->fields(array('claimed' => 0))
      ->execute();
  }

  /**
   * Clear signal.
   *
   * @param string $name
   *   The name of the job.
   * @param string $signal
   *   The name of the signal.
   */
  static public function clear($name, $signal) {
    $database = \Drupal::service('ultimate_cron.database_factory');
    $database->delete('ultimate_cron_signal')
      ->condition('job_name', $name)
      ->condition('signal_name', $signal)
      ->execute();
  }

  /**
   * Clear signals.
   *
   * @param string $name
   *   The name of the job.
   */
  static public function flush($name) {
    $database = \Drupal::service('ultimate_cron.database_factory');
    $database->delete('ultimate_cron_signal')
      ->condition('job_name', $name)
      ->execute();
  }
}
