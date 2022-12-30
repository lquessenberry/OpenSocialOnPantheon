<?php

namespace Drupal\data_policy;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Alters the container class.
 *
 * @package Drupal\data_policy
 */
class DataPolicyServiceProvider extends ServiceProviderBase {

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container) {
    $container->getDefinition('module_installer')
      ->setClass(DataPolicyModuleInstaller::class)
      ->addArgument(new Reference('entity_type.manager'))
      ->addArgument(new Reference('config.factory'));
  }

}
