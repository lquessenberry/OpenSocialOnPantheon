<?php

namespace Drupal\ultimate_cron;

use Drupal\Core\Queue\QueueWorkerManager;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Queue\RequeueException;
use Drupal\Core\Queue\SuspendQueueException;
use Drupal\Core\Queue\DelayableQueueInterface;
use Drupal\Core\Queue\DelayedRequeueException;

/**
 * Defines the queue worker.
 */
class QueueWorker {

  /**
   * Queue worker plugin manager
   *
   * @var Drupal\Core\Queue\QueueWorkerManager
   */
  protected $pluginManagerQueueWorker;

  /**
   * Queue Factory.
   *
   * @var Drupal\Core\Queue\QueueFactory
   */
  protected $queue;

  /**
   * Config Factory.
   *
   * @var Drupal\Core\Config\ConfigFactory
   */
  protected $configFactory;

  /**
   * Constructs a QueueWorker object.
   *
   * @param \Drupal\Core\Queue\QueueWorkerManager $plugin_manager_queue_worker
   * @param \Drupal\Core\Queue\QueueFactory $queue
   * @param \Drupal\Core\Config\ConfigFactory $config_factory
   */
  public function __construct(QueueWorkerManager $plugin_manager_queue_worker, QueueFactory $queue, ConfigFactory $config_factory) {
    $this->pluginManagerQueueWorker = $plugin_manager_queue_worker;
    $this->queue = $queue;
    $this->configFactory = $config_factory;
  }

  /**
   * Cron callback for queue worker cron jobs.
   */
  public function queueCallback(CronJobInterface $job) {
    $queue_name = str_replace(CronJobInterface::QUEUE_ID_PREFIX, '', $job->id());

    $queue_manager = $this->pluginManagerQueueWorker;
    $queue_factory = $this->queue;

    $config = $this->configFactory->get('ultimate_cron.settings');

    $info = $queue_manager->getDefinition($queue_name);

    // Make sure every queue exists. There is no harm in trying to recreate
    // an existing queue.
    $queue_factory->get($queue_name)->createQueue();

    /** @var \Drupal\Core\Queue\QueueWorkerInterface $queue_worker */
    $queue_worker = $queue_manager->createInstance($queue_name);
    $end = microtime(TRUE) + (isset($info['cron']['time']) ? $info['cron']['time'] : $config->get('queue.timeouts.time'));

    /** @var \Drupal\Core\Queue\QueueInterface $queue */
    $queue = $queue_factory->get($queue_name);
    $items = 0;
    while (microtime(TRUE) < $end) {
      // Check kill signal.
      if ($job->getSignal('kill')) {
        \Drupal::logger('ultimate_cron')->warning('Kill signal received for job @job_id', ['@job_id' => $job->id()]);
        break;
      }

      $item = $queue->claimItem($config->get('queue.timeouts.lease_time'));

      // If there is no item, check the empty delay setting and wait if
      // configured.
      if (!$item) {
        if ($config->get('queue.delays.empty_delay')) {
          usleep($config->get('queue.delays.empty_delay') * 1000000);
          continue;
        }
        else {
          break;
        }
      }

      try {
        // We have an item, check if we need to wait.
        if ($config->get('queue.delays.item_delay')) {
          if ($items == 0) {
            // Move the boundary if using a throttle,
            // to avoid waiting for nothing.
            $end -= $config->get('queue.delays.item_delay');
          }
          else {
            // Sleep before retrieving.
            usleep($config->get('queue.delays.item_delay') * 1000000);
          }
        }

        $queue_worker->processItem($item->data);
        $queue->deleteItem($item);
        $items++;
      }
      catch (RequeueException $e) {
        // The worker requested the task be immediately requeued.
        $queue->releaseItem($item);
      }
      catch (DelayedRequeueException $e) {
        if ($queue instanceof DelayableQueueInterface) {
          // This queue can handle a custom delay; use the duration provided
          // by the exception.
          $queue->delayItem($item, $e->getDelay());
        }
      }
      catch (SuspendQueueException $e) {
        // If the worker indicates there is a problem with the whole queue,
        // release the item and skip to the next queue.
        $queue->releaseItem($item);

        watchdog_exception('cron', $e);

        // Rethrow the SuspendQueueException, so that the queue is correctly
        // suspended for the current cron run to avoid infinite loops.
        throw $e;

      }
      catch (\Exception $e) {
        // In case of any other kind of exception, log it and leave the item
        // in the queue to be processed again later.
        watchdog_exception('ultimate_cron_queue', $e);
      }
    }
  }

}
