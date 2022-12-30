<?php

namespace Drupal\ultimate_cron\Logger;

use Drupal\Component\Plugin\ConfigurableInterface;
use Drupal\Component\Plugin\DependentPluginInterface;
use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Core\Plugin\PluginFormInterface;

/**
 * Defines a logger method.
 */
interface LoggerInterface extends PluginInspectionInterface, ConfigurableInterface, DependentPluginInterface, PluginFormInterface {

  /**
   * Returns the default configuration.
   *
   * @return mixed
   */
  public function defaultConfiguration();

  /**
   * Factory method for creating a new unsaved log entry object.
   *
   * @param string $name
   *   Name of the log entry (name of the job).
   *
   * @return LogEntry
   *   The log entry.
   */
  public function factoryLogEntry($name);

  /**
   * Create a new log entry.
   *
   * @param string $name
   *   Name of the log entry (name of the job).
   * @param string $lock_id
   *   The lock id.
   * @param string $init_message
   *   (optional) The initial message for the log entry.
   * @param int $log_type
   *   (optional) The log_type for the log entry.
   *
   * @return LogEntry
   *   The log entry created.
   */
  public function createEntry($name, $lock_id, $init_message = '', $log_type = ULTIMATE_CRON_LOG_TYPE_NORMAL);

  /**
   * Load latest log entry for multiple jobs.
   *
   * This is the fallback method. Loggers should implement an optimized
   * version if possible.
   *
   * @param array $jobs
   *   Jobs for which the log entries should be loaded.
   * @param array $log_types
   *   Type of log messages to load.
   */
  public function loadLatestLogEntries(array $jobs, array $log_types);

  /**
   * Load a log.
   *
   * @param string $name
   *   Name of log.
   * @param string $lock_id
   *   Specific lock id.
   *
   * @return \Drupal\ultimate_cron\Logger\LogEntry
   *   Log entry
   */
  public function load($name, $lock_id = NULL, array $log_types = [ULTIMATE_CRON_LOG_TYPE_NORMAL]);

  /**
   * Get page with log entries for a job.
   *
   * @param string $name
   *   Name of job.
   * @param array $log_types
   *   Log types to get.
   * @param int $limit
   *   (optional) Number of log entries per page.
   *
   * @return array
   *   Log entries.
   */
  public function getLogEntries($name, array $log_types, $limit = 10);

  /**
   * Saves a log entry.
   *
   * @param \Drupal\ultimate_cron\Logger\LogEntry $log_entry
   *   The log entry to save.
   */
  public function save(LogEntry $log_entry);

}
