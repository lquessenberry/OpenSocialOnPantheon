<?php

namespace Drupal\ultimate_cron\Scheduler;

use Drupal\Component\Plugin\ConfigurableInterface;
use Drupal\Component\Plugin\DependentPluginInterface;
use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\ultimate_cron\Entity\CronJob;

/**
 * Defines a scheduler method.
 */
interface SchedulerInterface extends PluginInspectionInterface, ConfigurableInterface, DependentPluginInterface, PluginFormInterface {

  /**
   * Returns the default configuration.
   *
   * @return mixed
   */
  public function defaultConfiguration();
  /**
   * Label for schedule.
   *
   * @param \Drupal\ultimate_cron\Entity\CronJob $job
   *   The job whose label should be formatted.
   */
  public function formatLabel(CronJob $job);

  /**
   * Label for schedule.
   *
   * @param \Drupal\ultimate_cron\Entity\CronJob $job
   *   The job whose label should be formatted.
   */
  public function formatLabelVerbose(CronJob $job);

  /**
   * Check job schedule.
   *
   * @param \Drupal\ultimate_cron\Entity\CronJob $job
   *   The job to check schedule for.
   *
   * @return bool
   *   TRUE if job is scheduled to run.
   */
  public function isScheduled(CronJob $job);

  /**
   * Check if job is behind schedule.
   *
   * @param \Drupal\ultimate_cron\Entity\CronJob $job
   *   The job to check schedule for.
   *
   * @return bool|int
   *   FALSE if job is behind its schedule or number of seconds behind.
   */
  public function isBehind(CronJob $job);

}
