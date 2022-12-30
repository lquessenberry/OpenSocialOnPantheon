<?php

namespace Drupal\ultimate_cron\Lock;


/**
 * Class for handling lock functions.
 *
 * This is a pseudo namespace really. Should probably be refactored...
 */
interface LockInterface {
  /**
   * Shutdown handler for releasing locks.
   */
  public function shutdown();

  /**
   * Relock.
   *
   * @param string $lock_id
   *   The lock id to relock.
   * @param float $timeout
   *   The timeout in seconds for the lock.
   *
   * @return boolean
   *   TRUE if relock was successful.
   */
  public function reLock($lock_id, $timeout = 30.0);

  /**
   * Dont release lock on shutdown.
   *
   * @param string $lock_id
   *   The lock id to persist.
   */
  public function persist($lock_id);

  /**
   * Check multiple locks.
   *
   * @param array $job_ids
   *   The names of the locks to check.
   *
   * @return array
   *   Array of lock ids.
   */
  public function isLockedMultiple($job_ids);

  /**
   * Acquire lock.
   *
   * @param string $job_id
   *   The name of the lock to acquire.
   * @param float $timeout
   *   The timeout in seconds for the lock.
   *
   * @return string
   *   The lock id acquired.
   */
  public function lock($job_id, $timeout = 30.0);

  /**
   * Check if lock is taken.
   *
   * @param string $job_id
   *   Name of the lock.
   * @param boolean $ignore_expiration
   *   Ignore expiration, just check if it's present.
   *   Used for retrieving the lock id of an expired lock.
   *
   * @return mixed
   *   The lock id if found, otherwise FALSE.
   */
  public function isLocked($job_id, $ignore_expiration = FALSE);

  /**
   * Release lock.
   *
   * @param string $lock_id
   *   The lock id to release.
   */
  public function unlock($lock_id);

  /**
   * Release lock if expired.
   *
   * Checks if expiration time has been reached, and releases the lock if so.
   *
   * @param string $job_id
   *   The name of the lock.
   */
  public function expire($job_id);

  /**
   * Cleanup expired locks.
   */
  public function cleanup();
}
