<?php

namespace Drupal\ultimate_cron\Lock;

use Drupal\Core\Database\Connection;
use Drupal\Core\Database\IntegrityConstraintViolationException;
use PDOException;

/**
 * Class for handling lock functions.
 *
 * This is a pseudo namespace really. Should probably be refactored...
 */
class Lock implements LockInterface {
  public $locks = NULL;

  public $killable = TRUE;

  private $connection;

  public function __construct(Connection $connection) {
    $this->connection = $connection;
  }

  /**
   * Shutdown handler for releasing locks.
   */
  public function shutdown() {
    if ($this->locks) {
      foreach (array_keys($this->locks) as $lock_id) {
        $this->unlock($lock_id);
      }
    }
  }

  /**
   * Dont release lock on shutdown.
   *
   * @param string $lock_id
   *   The lock id to persist.
   */
  public function persist($lock_id) {
    if (isset($this->locks)) {
      unset($this->locks[$lock_id]);
    }
  }

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
  public function lock($job_id, $timeout = 30.0) {
    // First, ensure cleanup.
    if (!isset($this->locks)) {
      $this->locks = array();
      ultimate_cron_register_shutdown_function(array(
        $this,
        'shutdown'
      ));
    }

    $this->connection->setTarget(_ultimate_cron_get_transactional_safe_connection());

    try {
      // First we ensure that previous locks are "removed"
      // if they are expired.
      $this->expire($job_id);

      // Ensure that the timeout is at least 1 ms.
      $timeout = max($timeout, 0.001);
      $expire = microtime(TRUE) + $timeout;

      // Now we try to acquire the lock.
      $lock_id = $this->connection->insert('ultimate_cron_lock')
        ->fields(array(
          'name' => $job_id,
          'current' => 0,
          'expire' => $expire,
        ))
      ->execute();

      $this->locks[$lock_id] = TRUE;

      return $lock_id;
    } catch (PDOException $e) {
      return FALSE;
    } catch (IntegrityConstraintViolationException $e) {
      return FALSE;
    }
  }

  /**
   * Release lock if expired.
   *
   * Checks if expiration time has been reached, and releases the lock if so.
   *
   * @param string $job_id
   *   The name of the lock.
   */
  public function expire($job_id) {
    if ($lock_id = $this->isLocked($job_id, TRUE)) {
      $now = microtime(TRUE);
      $this->connection->update('ultimate_cron_lock')
        ->expression('current', 'lid')
        ->condition('lid', $lock_id)
        ->condition('expire', $now, '<=')
        ->execute();
    }
  }

  /**
   * Release lock.
   *
   * @param string $lock_id
   *   The lock id to release.
   */
  public function unlock($lock_id) {
    $unlocked = $this->connection->update('ultimate_cron_lock')
      ->expression('current', 'lid')
      ->condition('lid', $lock_id)
      ->condition('current', 0)
      ->execute();
    $this->persist($lock_id);
    return $unlocked;
  }

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
  public function reLock($lock_id, $timeout = 30.0) {
    // Ensure that the timeout is at least 1 ms.
    $timeout = max($timeout, 0.001);
    $expire = microtime(TRUE) + $timeout;
    return (bool) $this->connection->update('ultimate_cron_lock')
      ->fields(array(
        'expire' => $expire,
      ))
      ->condition('lid', $lock_id)
      ->condition('current', 0)
      ->execute();
  }

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
  public function isLocked($job_id, $ignore_expiration = FALSE) {
    $now = microtime(TRUE);
    $result = $this->connection->select('ultimate_cron_lock', 'l')
      ->fields('l', array('lid', 'expire'))
      ->condition('name', $job_id)
      ->condition('current', 0)
      ->execute()
      ->fetchObject();
    return $result && ($result->expire > $now || $ignore_expiration) ? $result->lid : FALSE;
  }

  /**
   * Check multiple locks.
   *
   * @param array $job_ids
   *   The names of the locks to check.
   *
   * @return array
   *   Array of lock ids.
   */
  public function isLockedMultiple($job_ids) {
    $now = microtime(TRUE);
    $result = $this->connection->select('ultimate_cron_lock', 'l')
      ->fields('l', array('lid', 'name', 'expire'))
      ->condition('name', $job_ids, 'IN')
      ->condition('current', 0)
      ->execute()
      ->fetchAllAssoc('name');
    foreach ($job_ids as $job_id) {
      if (!isset($result[$job_id])) {
        $result[$job_id] = FALSE;
      }
      else {
        $result[$job_id] = $result[$job_id]->expire > $now ? $result[$job_id]->lid : FALSE;
      }
    }
    return $result;
  }

  /**
   * Cleanup expired locks.
   */
  public function cleanup() {
    $count = 0;
    $class = \Drupal::entityTypeManager()->getDefinition('ultimate_cron_job')->getClass();
    $now = microtime(TRUE);

    $this->connection->update('ultimate_cron_lock')
      ->expression('current', 'lid')
      ->condition('expire', $now, '<=')
      ->execute();

    do {
      $lids = $this->connection->select('ultimate_cron_lock', 'l')
        ->fields('l', array('lid'))
        ->where('l.current = l.lid')
        ->range(0, 100)
        ->execute()
        ->fetchCol();

      if ($lids) {
        $count += count($lids);
        $this->connection->delete('ultimate_cron_lock')
          ->condition('lid', $lids, 'IN')
          ->execute();
      }
      if ($job = $class::$currentJob) {
        if ($job->getSignal('kill')) {
          \Drupal::logger('ultimate_cron')->warning('kill signal recieved');
          return;
        }
      }
    } while ($lids);

    if ($count) {
      \Drupal::logger('ultimate_cron_lock')->info('Cleaned up @count expired locks', ['@count' => $count]);
    }
  }
}
