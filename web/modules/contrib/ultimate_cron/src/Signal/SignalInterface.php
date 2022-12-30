<?php

namespace Drupal\ultimate_cron\Signal;

interface SignalInterface {
  /**
   * Get a signal without claiming it.
   *
   * @param string $job_id
   *   The name of the job.
   * @param string $signal
   *   The name of the signal.
   *
   * @return string
   *   The signal if any.
   */
  public function peek($job_id, $signal);

  /**
   * Set signal.
   *
   * @param string $job_id
   *   The name of the job.
   * @param string $signal
   *   The name of the signal.
   *
   * @return boolean
   *   TRUE if the signal was set.
   */
  public function set($job_id, $signal);

  /**
   * Get and claim signal.
   *
   * @param $job_id
   * @param string $signal
   *   The name of the signal.
   *
   * @internal param string $name The name of the job.*   The name of the job.
   * @return string
   *   The signal if any. If a signal is found, it is "claimed" and therefore
   *   cannot be claimed again.
   */
  public function get($job_id, $signal);

  /**
   * Clear signals.
   *
   * @param string $job_id
   *   The name of the job.
   */
  public function flush($job_id);

  /**
   * Clear signal.
   *
   * @param string $job_id
   *   The name of the job.
   * @param string $signal
   *   The name of the signal.
   */
  public function clear($job_id, $signal);
}
