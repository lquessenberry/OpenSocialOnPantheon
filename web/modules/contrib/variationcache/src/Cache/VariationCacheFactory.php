<?php

namespace Drupal\variationcache\Cache;

use Drupal\Core\Cache\CacheFactoryInterface;
use Drupal\Core\Cache\Context\CacheContextsManager;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Defines the variation cache factory.
 *
 * @ingroup cache
 */
class VariationCacheFactory implements VariationCacheFactoryInterface {

  /**
   * Instantiated variation cache bins.
   *
   * @var \Drupal\variationcache\Cache\VariationCacheInterface[]
   */
  protected $bins = [];

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * The cache factory.
   *
   * @var \Drupal\Core\Cache\CacheFactoryInterface
   */
  protected $cacheFactory;

  /**
   * The cache contexts manager.
   *
   * @var \Drupal\Core\Cache\Context\CacheContextsManager
   */
  protected $cacheContextsManager;

  /**
   * Constructs a new VariationCacheFactory object.
   *
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack.
   * @param \Drupal\Core\Cache\CacheFactoryInterface $cache_factory
   *   The cache factory.
   * @param \Drupal\Core\Cache\Context\CacheContextsManager $cache_contexts_manager
   *   The cache contexts manager.
   */
  public function __construct(RequestStack $request_stack, CacheFactoryInterface $cache_factory, CacheContextsManager $cache_contexts_manager) {
    $this->requestStack = $request_stack;
    $this->cacheFactory = $cache_factory;
    $this->cacheContextsManager = $cache_contexts_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function get($bin) {
    if (!isset($this->bins[$bin])) {
      $this->bins[$bin] = new VariationCache($this->requestStack, $this->cacheFactory->get($bin), $this->cacheContextsManager);
    }
    return $this->bins[$bin];
  }

}
