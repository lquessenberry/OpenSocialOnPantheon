<?php

namespace Drupal\ultimate_cron\Launcher;

use Drupal\Component\Plugin\ConfigurableInterface;
use Drupal\Component\Plugin\DependentPluginInterface;
use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\ultimate_cron\CronJobInterface;

/**
 * Defines a launcher method.
 */
interface LauncherInterface extends PluginInspectionInterface, ConfigurableInterface, DependentPluginInterface, PluginFormInterface {

  /**
   * Default settings.
   *
   * @return array
   *   Returns array with default configuration of the object.
   */
  public function defaultConfiguration();

  /**
   * Lock job.
   *
   * @param \Drupal\ultimate_cron\CronJobInterface $job
   *   The job to lock.
   *
   * @return string|FALSE
   *   Lock ID or FALSE.
   */
  public function lock(CronJobInterface $job);

  /**
   * Unlock a lock.
   *
   * @param string $lock_id
   *   The lock id to unlock.
   * @param bool $manual
   *   Whether this is a manual unlock or not.
   *
   * @return bool
   *   TRUE on successful unlock.
   */
  public function unlock($lock_id, $manual = FALSE);

  /**
   * Check if a job is locked.
   *
   * @param \Drupal\ultimate_cron\CronJobInterface $job
   *   The job to check.
   *
   * @return string
   *   Lock ID of the locked job, FALSE if not locked.
   */
  public function isLocked(CronJobInterface $job);

  /**
   * Launch job.
   *
   * @param \Drupal\ultimate_cron\CronJobInterface $job
   *   The job to launch.
   *
   * @return bool
   *   TRUE on successful launch.
   */
  public function launch(CronJobInterface $job);

  /**
   * Fallback implementation of multiple lock check.
   *
   * Each launcher should implement an optimized version of this method
   * if possible.
   *
   * @param \Drupal\ultimate_cron\CronJobInterface[] $jobs
   *   Array of UltimateCronJobs to check.
   *
   * @return array
   *   Array of lock ids, keyed by job name.
   */
  public function isLockedMultiple(array $jobs);

  /**
   * Default implementation of jobs launcher.
   *
   * @param \Drupal\ultimate_cron\CronJobInterface[] $jobs
   *   Array of UltimateCronJobs to launch.
   */
  public function launchJobs(array $jobs);

  /**
   * Format running state.
   *
   * @param \Drupal\ultimate_cron\CronJobInterface $job
   *   The running job to format.
   */
  public function formatRunning(CronJobInterface $job);

  /**
   * Format unfinished state.
   *
   * @param \Drupal\ultimate_cron\CronJobInterface $job
   *   The running job to format.
   */
  public function formatUnfinished(CronJobInterface $job);

  /**
   * Default implementation of formatProgress().
   *
   * @param \Drupal\ultimate_cron\CronJobInterface $job
   *   Job to format progress for.
   * @param string $progress
   *   Progress value for the Job.
   */
  public function formatProgress(CronJobInterface $job, $progress);

  /**
   * Default implementation of initializeProgress().
   *
   * @param \Drupal\ultimate_cron\CronJobInterface $job
   *   Job to initialize progress for.
   */
  public function initializeProgress(CronJobInterface $job);

  /**
   * Default implementation of finishProgress().
   *
   * @param \Drupal\ultimate_cron\CronJobInterface $job
   *   Job to finish progress for.
   */
  public function finishProgress(CronJobInterface $job);

  /**
   * Default implementation of getProgress().
   *
   * @param \Drupal\ultimate_cron\CronJobInterface $job
   *   Job to get progress for.
   *
   * @return float
   *   Progress for the job.
   */
  public function getProgress(CronJobInterface $job);

  /**
   * Default implementation of getProgressMultiple().
   *
   * @param \Drupal\ultimate_cron\CronJobInterface[] $jobs
   *   Jobs to get progresses for, keyed by job name.
   *
   * @return array
   *   Progresses, keyed by job name.
   */
  public function getProgressMultiple(array $jobs);

  /**
   * Default implementation of setProgress().
   *
   * @param \Drupal\ultimate_cron\CronJobInterface $job
   *   Job to set progress for.
   * @param float $progress
   *   Progress (0-1).
   */
  public function setProgress(CronJobInterface $job, $progress);

}
