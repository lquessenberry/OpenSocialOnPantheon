<?php

namespace Drupal\ultimate_cron\Logger;

use Drupal\ultimate_cron\CronPlugin;

/**
 * Abstract class for Ultimate Cron loggers
 *
 * Each logger must implement its own functions for getting/setting data
 * from the its storage backend.
 *
 * Abstract methods:
 *   load($name, $lock_id = NULL)
 *     - Load a log entry. If no $lock_id is provided, this method should
 *       load the latest log entry for $name.
 *
 * "Abstract" properties:
 *   $logEntryClass
 *     - The class name of the log entry class associated with this logger.
 */
abstract class LoggerBase extends CronPlugin implements LoggerInterface {
  static public $log_entries = NULL;

  /**
   * {@inheritdoc}
   */
  public function factoryLogEntry($name) {
    return new LogEntry($name, $this);
  }

  /**
   * {@inheritdoc}
   */
  public function createEntry($name, $lock_id, $init_message = '', $log_type = ULTIMATE_CRON_LOG_TYPE_NORMAL) {
    $log_entry = new LogEntry($name, $this, $log_type);

    $log_entry->lid = $lock_id;
    $log_entry->start_time = microtime(TRUE);
    $log_entry->init_message = $init_message;
    //$log_entry->save();
    return $log_entry;
  }

  /**
   * {@inheritdoc}
   */
  public function loadLatestLogEntries(array $jobs, array $log_types) {
    $logs = array();
    foreach ($jobs as $job) {
      $logs[$job->id()] = $job->loadLatestLogEntry($log_types);
    }
    return $logs;
  }

}
