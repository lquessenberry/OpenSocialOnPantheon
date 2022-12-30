<?php

namespace Drupal\ultimate_cron;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Defines service provider for ultimate cron.
 */
class UltimateCronServiceProvider extends ServiceProviderBase {

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container) {
    // Overrides cron class to use our own cron manager.
    $container->getDefinition('cron')
      ->setClass('Drupal\ultimate_cron\UltimateCron')
      ->addMethodCall('setConfigFactory', [new Reference('config.factory')]);
  }

}
