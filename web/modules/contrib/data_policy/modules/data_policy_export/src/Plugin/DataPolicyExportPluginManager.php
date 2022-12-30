<?php

namespace Drupal\data_policy_export\Plugin;

use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\data_policy_export\Annotation\DataPolicyExportPlugin;

/**
 * Provides the Data Policy export plugin plugin manager.
 */
class DataPolicyExportPluginManager extends DefaultPluginManager {

  /**
   * Constructs a new DataPolicyExportPluginManager object.
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
    parent::__construct('Plugin/DataPolicyExportPlugin', $namespaces, $module_handler, DataPolicyExportPluginInterface::class, DataPolicyExportPlugin::class);
    $this->alterInfo('data_policy_export_plugin_info');
    $this->setCacheBackend($cache_backend, 'data_policy_export_data_policy_export_plugin_plugins');
  }

}
