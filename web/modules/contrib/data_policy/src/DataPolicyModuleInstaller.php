<?php

namespace Drupal\data_policy;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\DrupalKernelInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Extension\ModuleInstaller;
use Drupal\Core\Database\Connection;
use Drupal\Core\Update\UpdateHookRegistry;

/**
 * Class to handle the module installation and uninstallation.
 *
 * @package Drupal\data_policy
 */
class DataPolicyModuleInstaller extends ModuleInstaller {

  /**
   * The data policy storage.
   *
   * @var \Drupal\data_policy\DataPolicyStorageInterface
   */
  protected $storage;

  /**
   * The data policy entity ID.
   *
   * @var int
   */
  protected $entityId;

  /**
   * Constructs a new DataPolicyModuleInstaller instance.
   *
   * @param string $root
   *   The app root.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   * @param \Drupal\Core\DrupalKernelInterface $kernel
   *   The drupal kernel.
   * @param \Drupal\Core\Database\Connection $connection
   *   The database connection.
   * @param \Drupal\Core\Update\UpdateHookRegistry|null $update_registry
   *   (Optional) The update registry service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   */
  public function __construct($root, ModuleHandlerInterface $module_handler, DrupalKernelInterface $kernel, Connection $connection, UpdateHookRegistry $update_registry, EntityTypeManagerInterface $entity_type_manager, ConfigFactoryInterface $config_factory) {
    parent::__construct($root, $module_handler, $kernel, $connection, $update_registry);

    $this->storage = $entity_type_manager->getStorage('data_policy');

    $this->entityId = $config_factory->get('data_policy.data_policy')
      ->get('entity_id');
  }

  /**
   * {@inheritdoc}
   */
  public function uninstall(array $module_list, $uninstall_dependents = TRUE) {
    if (in_array('data_policy', $module_list) && $this->entityId) {
      $this->storage->load($this->entityId)->delete();
    }

    return parent::uninstall($module_list, $uninstall_dependents);
  }

}
