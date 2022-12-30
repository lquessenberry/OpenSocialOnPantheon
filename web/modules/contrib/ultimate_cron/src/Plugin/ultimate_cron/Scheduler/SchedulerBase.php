<?php

namespace Drupal\ultimate_cron\Plugin\ultimate_cron\Scheduler;
use Drupal\ultimate_cron\Entity\CronJob;
use Drupal\ultimate_cron\CronPlugin;
use Drupal\ultimate_cron\Scheduler\SchedulerInterface;

/**
 * Abstract class for Ultimate Cron schedulers
 *
 * A scheduler is responsible for telling Ultimate Cron whether a job should
 * run or not.
 *
 * Abstract methods:
 *   isScheduled($job)
 *     - Check if the given job is scheduled for launch at this time.
 *       TRUE if it's scheduled for launch, otherwise FALSE.
 *
 *   isBehind($job)
 *     - Check if the given job is behind its schedule.
 *       FALSE if not behind, otherwise the amount of time it's behind
 *       in seconds.
 */
abstract class SchedulerBase extends CronPlugin implements SchedulerInterface {
  /**
   * Check job schedule.
   *
   * @param CronJob $job
   *   The job to check schedule for.
   *
   * @return boolean
   *   TRUE if job is scheduled to run.
   */
  abstract public function isScheduled(CronJob $job);

  /**
   * Check if job is behind schedule.
   *
   * @param \Drupal\ultimate_cron\Entity\CronJob $job
   *   The job to check schedule for.
   *
   * @return bool|int
   *   FALSE if job is behind its schedule or number of seconds behind.
   */
  abstract public function isBehind(CronJob $job);
}
