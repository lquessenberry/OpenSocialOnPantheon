<?php

namespace Drupal\typed_data;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\typed_data\Annotation\DataFilter;

/**
 * Manager for data filter plugins.
 *
 * @see \Drupal\typed_data\DataFilterInterface
 */
class DataFilterManager extends DefaultPluginManager implements DataFilterManagerInterface {

  /**
   * {@inheritdoc}
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler) {
    $this->alterInfo('typed_data_filter');
    parent::__construct('Plugin/TypedDataFilter', $namespaces, $module_handler, DataFilterInterface::class, DataFilter::class);
    $this->setCacheBackend($cache_backend, 'typed_data_filter_plugins');
  }

}
