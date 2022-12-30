<?php

namespace Drupal\ultimate_cron\Signal;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\ultimate_cron\Signal\SignalInterface;

class SignalCache implements SignalInterface {

  /**
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  public $cacheBackend;

  /**
   * @var \Drupal\Core\Lock\LockBackendInterface
   */
  public $lockBackend;

  public function __construct(CacheBackendInterface $cache_backend, LockBackendInterface $lock_backend) {
    $this->cacheBackend = $cache_backend;
    $this->lockBackend = $lock_backend;
  }

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
  public function peek($job_id, $signal) {
    $cache = $this->cacheBackend->get("signal-$job_id-$signal");
    if ($cache) {
      $flushed = $this->cacheBackend->get("flushed-$job_id");
      if (!$flushed || $cache->created > $flushed->created) {
        return $cache->data;
      }
    }
    return FALSE;
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
  public function get($job_id, $signal) {
    if ($this->lockBackend->acquire("signal-$job_id-$signal")) {
      $result = self::peek($job_id, $signal);
      self::clear($job_id, $signal);
      $this->lockBackend->release("signal-$job_id-$signal");
      return $result;
    }
    return FALSE;
  }

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
  public function set($job_id, $signal) {
    $this->cacheBackend->set("signal-$job_id-$signal", TRUE);
  }

  /**
   * Clear signal.
   *
   * @param string $job_id
   *   The name of the job.
   * @param string $signal
   *   The name of the signal.
   */
  public function clear($job_id, $signal) {
    $this->cacheBackend->delete("signal-$job_id-$signal");
  }

  /**
   * Clear signals.
   *
   * @param string $job_id
   *   The name of the job.
   */
  public function flush($job_id) {
    $this->cacheBackend->set("flushed-$job_id", microtime(TRUE));
  }
}
