<?php

namespace Drupal\ultimate_cron\Launcher;

use Drupal\ultimate_cron\CronPlugin;
use Drupal\ultimate_cron\CronJobInterface;

/**
 * Abstract class for Ultimate Cron launchers.
 *
 * A launcher is responsible for locking and launching/running a job.
 *
 * Abstract methods:
 *   lock($job)
 *     - Lock a job. This method must return the lock_id on success
 *       or FALSE on failure.
 *
 *   unlock($lock_id, $manual = FALSE)
 *     - Release a specific lock id. If $manual is set, then the release
 *       was triggered manually by a user.
 *
 *   isLocked($job)
 *     - Check if a job is locked. This method must return the current
 *     - lock_id for the given job, or FALSE if it is not locked.
 *
 *   launch($job)
 *     - This method launches/runs the given job. This method must handle
 *       the locking of job before launching it. Returns TRUE on successful
 *       launch, FALSE if not.
 *
 * Important methods:
 *   isLockedMultiple($jobs)
 *     - Check locks for multiple jobs. Each launcher should implement an
 *       optimized version of this method if possible.
 *
 *   launchJobs($jobs)
 *     - Launches the jobs provided to it. A default implementation of this
 *       exists, but can be overridden. It is assumed that this function
 *       checks the jobs schedule before launching and that it also handles
 *       locking wrt concurrency for the launcher itself.
 *
 */
abstract class LauncherBase extends CronPlugin implements LauncherInterface {

  /**
   * {@inheritdoc}
   */
  public function isLockedMultiple(array $jobs) {
    $lock_ids = array();
    foreach ($jobs as $name => $job) {
      $lock_ids[$name] = $this->isLocked($job);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function launchJobs(array $jobs) {
    foreach ($jobs as $job) {
      if ($job->isScheduled()) {
        $job->launch();
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function formatRunning(CronJobInterface $job) {
    $file = \Drupal::service('extension.list.module')->getPath('ultimate_cron') . '/icons/hourglass.png';
    $status = ['#theme' => 'image', '#uri' => $file];
    $title = t('running');
    return array($status, $title);
  }

  /**
   * {@inheritdoc}
   */
  public function formatUnfinished(CronJobInterface $job) {
    $file = \Drupal::service('extension.list.module')->getPath('ultimate_cron') . '/icons/lock_open.png';
    $status = ['#theme' => 'image', '#uri' => $file];
    $title = t('unfinished but not locked?');
    return array($status, $title);
  }

  /**
   * {@inheritdoc}
   */
  public function formatProgress(CronJobInterface $job, $progress) {
    $progress = $progress ? sprintf("(%d%%)", round($progress * 100)) : '';
    return $progress;
  }

  /**
   * {@inheritdoc}
   */
  public function initializeProgress(CronJobInterface $job) {
    \Drupal::service('ultimate_cron.progress')->setProgress($job->id(), FALSE);
  }

  /**
   * {@inheritdoc}
   */
  public function finishProgress(CronJobInterface $job) {
    \Drupal::service('ultimate_cron.progress')->setProgress($job->id(), FALSE);
  }

  /**
   * {@inheritdoc}
   */
  public function getProgress(CronJobInterface $job) {
    return \Drupal::service('ultimate_cron.progress')->getProgress($job->id());
  }

  /**
   * {@inheritdoc}
   */
  public function getProgressMultiple(array $jobs) {
    return \Drupal::service('ultimate_cron.progress')->getProgressMultiple(array_keys($jobs));
  }

  /**
   * {@inheritdoc}
   */
  public function setProgress(CronJobInterface $job, $progress) {
    \Drupal::service('ultimate_cron.progress')->setProgress($job->id(), $progress);
  }

}
