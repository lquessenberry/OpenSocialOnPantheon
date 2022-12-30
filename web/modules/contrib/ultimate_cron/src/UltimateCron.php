<?php

namespace Drupal\ultimate_cron;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Cron;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Queue\QueueWorkerManagerInterface;
use Drupal\Core\Session\AccountSwitcherInterface;
use Drupal\Core\State\StateInterface;
use Drupal\ultimate_cron\Entity\CronJob;
use Psr\Log\LoggerInterface;

/**
 * The Ultimate Cron service.
 */
class UltimateCron extends Cron {

  /**
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Sets the config factory for ultimate cron service.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   */
  public function setConfigFactory(ConfigFactoryInterface $config_factory) {
    $this->configFactory = $config_factory;
  }

  /**
   * {@inheritdoc}
   */
  public function run() {

    // Load the cron jobs in the right order.
    $job_ids = \Drupal::entityQuery('ultimate_cron_job')
      ->condition('status', TRUE)
      ->sort('weight', 'ASC')

      ->execute();

    $launcher_jobs = array();
    foreach (CronJob::loadMultiple($job_ids) as $job) {
      /* @var \Drupal\Core\Plugin\DefaultPluginManager $manager */
      $manager = \Drupal::service('plugin.manager.ultimate_cron.' . 'launcher');
      $launcher = $manager->createInstance($job->getLauncherId());
      $launcher_definition = $launcher->getPluginDefinition();

      if (!isset($launchers) || in_array($launcher->getPluginId(), $launchers)) {
        $launcher_jobs[$launcher_definition['id']]['launcher'] = $launcher;
        $launcher_jobs[$launcher_definition['id']]['sort'] = array($launcher_definition['weight']);
        $launcher_jobs[$launcher_definition['id']]['jobs'][$job->id()] = $job;
        $launcher_jobs[$launcher_definition['id']]['jobs'][$job->id()]->sort = array($job->loadLatestLogEntry()->start_time);
      }
    }

    foreach ($launcher_jobs as $name => $launcher_job) {
      $launcher_job['launcher']->launchJobs($launcher_job['jobs']);
    }

    // Run standard queue processing if our own handling is disabled.
    if (!$this->configFactory->get('ultimate_cron.settings')->get('queue.enabled')) {
      $this->processQueues();
    }

    $this->setCronLastTime();

    return TRUE;
  }
}
