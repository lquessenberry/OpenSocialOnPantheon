<?php

namespace Drupal\group;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;
use Symfony\Component\DependencyInjection\Definition;

/**
 * Alters existing services for the Group module.
 */
class GroupServiceProvider extends ServiceProviderBase {

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container) {
    // Ensures you can update to enable VariationCache without the container
    // choking on the variation_cache_factory service no longer being there.
    if (!$container->hasDefinition('variation_cache_factory')) {
      $definition = new Definition('\Drupal\group\VariationCacheFactoryUpdateFix');
      $definition->setPublic(TRUE);
      $container->addDefinitions(['variation_cache_factory' => $definition]);
    }
  }

}
