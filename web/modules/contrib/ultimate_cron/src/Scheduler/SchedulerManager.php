<?php

namespace Drupal\ultimate_cron\Scheduler;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\payment\Plugin\Payment\OperationsProviderPluginManagerTrait;

/**
 * A plugin manager for scheduler plugins.
 *
 *  @see \Drupal\ultimate_cron\Scheduler\SchedulerInterface
 */
class SchedulerManager extends DefaultPluginManager {

  /**
   * Constructs a SchedulerManager object.
   *
   * @param \Traversable $namespaces
   *   An object that implements \Traversable which contains the root paths
   *   keyed by the corresponding namespace to look for plugin implementations.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   Cache backend instance to use.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler to invoke the alter hook with.
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler) {
    parent::__construct('Plugin/ultimate_cron/Scheduler', $namespaces, $module_handler, '\Drupal\ultimate_cron\Scheduler\SchedulerInterface', 'Drupal\ultimate_cron\Annotation\SchedulerPlugin');
    $this->alterInfo('ultimate_cron_scheduler_info');
    $this->setCacheBackend($cache_backend, 'ultimate_cron_scheduler');
  }

}
