<?php

namespace Drupal\ultimate_cron;

use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\ultimate_cron\Logger\LogEntry;
use Drupal\ultimate_cron\Logger\LoggerBase;

interface CronJobInterface extends ConfigEntityInterface {

  /**
   * Cron job ID prefix for queue jobs.
   */
  const QUEUE_ID_PREFIX = 'ultimate_cron_queue_';

  /**
   * Get locked state for multiple jobs.
   *
   * @param array $jobs
   *   Jobs to check locks for.
   */
  public static function isLockedMultiple($jobs);

  /**
   * Load latest log entries.
   *
   * @param array $jobs
   *   Jobs to load log entries for.
   *
   * @return array
   *   Array of UltimateCronLogEntry objects.
   */
  public static function loadLatestLogEntries($jobs, $log_types = array(ULTIMATE_CRON_LOG_TYPE_NORMAL));

  /**
   * Get multiple job progresses.
   *
   * @param array $jobs
   *   Jobs to get progress for.
   *
   * @return array
   *   Progress of jobs, keyed by job name.
   */
  public static function getProgressMultiple($jobs);

  /**
   * Gets the title of the created cron job.
   *
   * @return mixed
   *  Cron job title.
   */
  public function getTitle();

  /**
   * Gets the cron job callback string.
   *
   * @return string
   *  Callback string.
   */
  public function getCallback();

  /**
   * Gets the cron job module name used for the callback string.
   *
   * @return string
   *  Module name.
   */
  public function getModule();

  /**
   * Gets scheduler array which holds info about the scheduler settings.
   *
   * @return array
   *  Scheduler settings
   */
  public function getSchedulerId();

  /**
   * Gets launcher array which holds info about the launcher settings.
   *
   * @return array
   *  Launcher settings
   */
  public function getLauncherId();

  /**
   * Gets logger array which holds info about the logger settings.
   *
   * @return array
   *  Logger settings
   */
  public function getLoggerId();

  /**
   * Sets the title of the created cron job.
   *
   * @param $title
   * @return mixed
   *  Cron job title.
   */
  public function setTitle($title);

  /**
   * Sets the cron job callback string.
   *
   * @param $callback
   * @return string
   *  Callback string.
   */
  public function setCallback($callback);

  /**
   * Sets the cron job module name used for the callback string.
   *
   * @param $module
   * @return string
   *  Module name.
   */
  public function setModule($module);

  /**
   * Sets scheduler array which holds info about the scheduler settings.
   *
   * @param $scheduler_id
   * @return array
   *  Scheduler settings
   */
  public function setSchedulerId($scheduler_id);

  /**
   * Sets launcher array which holds info about the launcher settings.
   *
   * @param $launcher_id
   * @return array
   *  Launcher settings
   */
  public function setLauncherId($launcher_id);

  /**
   * Sets logger array which holds info about the logger settings.
   *
   * @param $logger_id
   * @return array
   *  Logger settings
   */
  public function setLoggerId($logger_id);

  /**
   * Check if the cron job is callable.
   *
   * @return bool
   *   TRUE if the job is callable, FALSE otherwise.
   */
  public function isValid();

  /**
   * Get a signal without affecting it.
   *
   * @see UltimateCronSignal::peek()
   */
  public function peekSignal($signal);

  /**
   * Get a signal and clear it if found.
   *
   * @see UltimateCronSignal::get()
   */
  public function getSignal($signal);

  /**
   * Send a signal.
   *
   * @see UltimateCronSignal::set()
   */
  public function sendSignal($signal, $persist = FALSE);

  /**
   * Clear a signal.
   *
   * @see UltimateCronSignal::clear()
   */
  public function clearSignal($signal);

  /**
   * Send all signal for the job.
   *
   * @see UltimateCronSignal::flush()
   */
  public function clearSignals();

  /**
   * Check job schedule.
   */
  public function isScheduled();

  /**
   * Check if job is behind its schedule.
   */
  public function isBehindSchedule();

  /**
   * Lock job.
   */
  public function lock();

  /**
   * Unlock job.
   *
   * @param string $lock_id
   *   The lock id to unlock.
   * @param boolean $manual
   *   Whether or not this is a manual unlock.
   */
  public function unlock($lock_id = NULL, $manual = FALSE);

  /**
   * Get locked state of job.
   */
  public function isLocked();

  /**
   * Run job.
   *
   * @param string $init_message
   *   (optional) The launch message. If left NULL, a default message will be
   *   displayed.
   *
   * @return bool
   *   TRUE if the job is ran, FALSE otherwise.
   */
  public function run($init_message = NULL);

  /**
   * Get log entries.
   *
   * @param integer $limit
   *   (optional) Number of log entries per page.
   *
   * @return array
   *   Array of UltimateCronLogEntry objects.
   */
  public function getLogEntries($log_types = ULTIMATE_CRON_LOG_TYPE_ALL, $limit = 10);

  /**
   * Load log entry.
   *
   * @param string $lock_id
   *   The lock id of the log entry.
   *
   * @return LogEntry
   *   The log entry.
   */
  public function loadLogEntry($lock_id);

  /**
   * Load latest log.
   *
   * @return LogEntry
   *   The latest log entry for this job.
   */
  public function loadLatestLogEntry($log_types = array(ULTIMATE_CRON_LOG_TYPE_NORMAL));

  /**
   * Start logging.
   *
   * @param string $lock_id
   *   The lock id to use.
   * @param string $init_message
   *   Initial message for the log.
   *
   * @return \Drupal\ultimate_cron\Logger\LogEntry
   *   The log object.
   */
  public function startLog($lock_id, $init_message = '', $log_type = ULTIMATE_CRON_LOG_TYPE_NORMAL);

  /**
   * Resume a previosly saved log.
   *
   * @param string $lock_id
   *   The lock id of the log to resume.
   *
   * @return LogEntry
   *   The log entry object.
   */
  public function resumeLog($lock_id);

  /**
   * Get module name for this job.
   */
  public function getModuleName();

  /**
   * Get module description for this job.
   */
  public function getModuleDescription();

  /**
   * Initialize progress.
   */
  public function initializeProgress();

  /**
   * Finish progress.
   */
  public function finishProgress();

  /**
   * Get job progress.
   *
   * @return float
   *   The progress of this job.
   */
  public function getProgress();

  /**
   * Set job progress.
   *
   * @param float $progress
   *   The progress (0 - 1).
   */
  public function setProgress($progress);

  /**
   * Format progress.
   *
   * @param float $progress
   *   (optional) The progress to format. Uses the progress on the object
   *              if not specified.
   *
   * @return string
   *   Formatted progress.
   */
  public function formatProgress($progress = NULL);

  /**
   * Get a "unique" id for a job.
   */
  public function getUniqueID();

  /**
   * Get job plugin.
   *
   * If no plugin name is provided current plugin of the specified type will
   * be returned.
   *
   * @param string $plugin_type
   *   Name of plugin type.
   * @param string $name
   *   (optional) The name of the plugin.
   *
   * @return \Drupal\ultimate_cron\CronPlugin
   *   Plugin instance of the specified type.
   */
  public function getPlugin($plugin_type, $name = NULL);

}
