<?php

namespace Drupal\variationcache\Cache;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheableDependencyInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Cache\Context\CacheContextsManager;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Wraps a regular cache backend to make it support cache contexts.
 *
 * @ingroup cache
 */
class VariationCache implements VariationCacheInterface {

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * The cache backend to wrap.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected $cacheBackend;

  /**
   * The cache contexts manager.
   *
   * @var \Drupal\Core\Cache\Context\CacheContextsManager
   */
  protected $cacheContextsManager;

  /**
   * Constructs a new VariationCache object.
   *
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   The cache backend to wrap.
   * @param \Drupal\Core\Cache\Context\CacheContextsManager $cache_contexts_manager
   *   The cache contexts manager.
   */
  public function __construct(RequestStack $request_stack, CacheBackendInterface $cache_backend, CacheContextsManager $cache_contexts_manager) {
    $this->requestStack = $request_stack;
    $this->cacheBackend = $cache_backend;
    $this->cacheContextsManager = $cache_contexts_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function get(array $keys, CacheableDependencyInterface $initial_cacheability) {
    $chain = $this->getRedirectChain($keys, $initial_cacheability);
    return array_pop($chain);
  }

  /**
   * {@inheritdoc}
   */
  public function set(array $keys, $data, CacheableDependencyInterface $cacheability, CacheableDependencyInterface $initial_cacheability) {
    $initial_contexts = $initial_cacheability->getCacheContexts();
    $contexts = $cacheability->getCacheContexts();

    if (array_diff($initial_contexts, $contexts)) {
      throw new \LogicException('The complete set of cache contexts for a variation cache item must contain all of the initial cache contexts.');
    }

    // Don't store uncacheable items.
    if ($cacheability->getCacheMaxAge() === 0) {
      return;
    }

    // Track the potential effect of cache context folding on cache tags.
    $folded_cacheability = CacheableMetadata::createFromObject($cacheability);
    $cid = $this->createCacheId($keys, $folded_cacheability);

    // Check whether we had any cache redirects leading to the cache ID already.
    // If there are none, we know that there is no proper redirect path to the
    // cache ID we're trying to store the data at. This may be because there is
    // either no full redirect path yet or there is one that is too specific at
    // a given step of the way. In case of the former, we simply need to store a
    // redirect. In case of the latter, we need to replace the overly specific
    // step with a simpler one.
    $chain = $this->getRedirectChain($keys, $initial_cacheability);
    if (!array_key_exists($cid, $chain)) {
      // We can easily find overly specific redirects by comparing their cache
      // contexts to the ones we have here. If a redirect has more or different
      // contexts, it needs to be replaced with a simplified version.
      //
      // Simplifying overly specific redirects can be done in two ways:
      //
      // -------
      //
      // Problem: The redirect is a superset of the current cache contexts.
      // Solution: We replace the redirect with the current contexts.
      //
      // Example: Suppose we try to store an object with context A, whereas we
      // already have a redirect that uses A and B. In this case we simply store
      // the object at the address designated by context A and next time someone
      // tries to load the initial AB object, it will restore its redirect path
      // by adding an AB redirect step after A.
      //
      // -------
      //
      // Problem: The redirect overlaps, with both options having unique values.
      // Solution: Find the common contexts and use those for a new redirect.
      //
      // Example: Suppose we try to store an object with contexts A and C, but
      // we already have a redirect that uses A and B. In this case we find A to
      // be the common cache context and replace the redirect with one only
      // using A, immediately followed by one for AC so there is a full path to
      // the data we're trying to set. Next time someone tries to load the
      // initial AB object, it will restore its redirect path by adding an AB
      // redirect step after A.
      foreach ($chain as $chain_cid => $result) {
        if ($result && $result->data instanceof CacheRedirect) {
          $result_contexts = $result->data->getCacheContexts();
          if (array_diff($result_contexts, $contexts)) {
            // Check whether we have an overlap scenario as we need to manually
            // create an extra redirect in that case.
            $common_contexts = array_intersect($result_contexts, $contexts);
            if ($common_contexts != $contexts) {
              // Set the redirect to the common contexts at the current address.
              // In the above example this is essentially overwriting the
              // redirect to AB with a redirect to A.
              $common_cacheability = (new CacheableMetadata())->setCacheContexts($common_contexts);
              $this->cacheBackend->set($chain_cid, new CacheRedirect($common_cacheability));

              // Before breaking the loop, set the current address to the next
              // one in line so that we can store the full redirect as well. In
              // the above example, this is the part where we immediately also
              // store a redirect to AC at the CID that A pointed to.
              $chain_cid = $this->createCacheIdFast($keys, $common_cacheability);
            }
            break;
          }
        }
      }

      // The loop above either broke at an overly specific step or completed
      // without any problem. In both cases, $chain_cid ended up with the value
      // that we should store the new redirect at.
      //
      // Cache redirects are stored indefinitely and without tags as they never
      // need to be cleared. If they ever end up leading to a stale cache item
      // that now uses different contexts then said item will either follow an
      // existing path of redirects or carve its own over the old one.
      $this->cacheBackend->set($chain_cid, new CacheRedirect($cacheability));
    }

    $this->cacheBackend->set($cid, $data, $this->maxAgeToExpire($cacheability->getCacheMaxAge()), $folded_cacheability->getCacheTags());
  }

  /**
   * {@inheritdoc}
   */
  public function delete(array $keys, CacheableDependencyInterface $initial_cacheability) {
    $chain = $this->getRedirectChain($keys, $initial_cacheability);
    end($chain);
    $this->cacheBackend->delete(key($chain));
  }

  /**
   * {@inheritdoc}
   */
  public function invalidate(array $keys, CacheableDependencyInterface $initial_cacheability) {
    $chain = $this->getRedirectChain($keys, $initial_cacheability);
    end($chain);
    $this->cacheBackend->invalidate(key($chain));
  }

  /**
   * Performs a full get, returning every step of the way.
   *
   * This will check whether there is a cache redirect and follow it if so. It
   * will keep following redirects until it gets to a cache miss or the actual
   * cache object.
   *
   * @param string[] $keys
   *   The cache keys to retrieve the cache entry for.
   * @param \Drupal\Core\Cache\CacheableDependencyInterface $initial_cacheability
   *   The cache metadata of the data to store before other systems had a chance
   *   to adjust it. This is also commonly known as "pre-bubbling" cacheability.
   *
   * @return array
   *   Every cache get that lead to the final result, keyed by the cache ID used
   *   to query the cache for that result.
   */
  protected function getRedirectChain(array $keys, CacheableDependencyInterface $initial_cacheability) {
    $cid = $this->createCacheIdFast($keys, $initial_cacheability);
    $chain[$cid] = $result = $this->cacheBackend->get($cid);

    while ($result && $result->data instanceof CacheRedirect) {
      $cid = $this->createCacheIdFast($keys, $result->data);
      $chain[$cid] = $result = $this->cacheBackend->get($cid);
    }

    return $chain;
  }

  /**
   * Maps a max-age value to an "expire" value for the Cache API.
   *
   * @param int $max_age
   *   A max-age value.
   *
   * @return int
   *   A corresponding "expire" value.
   *
   * @see \Drupal\Core\Cache\CacheBackendInterface::set()
   */
  protected function maxAgeToExpire($max_age) {
    if ($max_age !== Cache::PERMANENT) {
      return (int) $this->requestStack->getMainRequest()->server->get('REQUEST_TIME') + $max_age;
    }
    return $max_age;
  }

  /**
   * Creates a cache ID based on cache keys and cacheable metadata.
   *
   * If cache contexts are folded during the creating of the cache ID, then the
   * effect of said folding on the cache contexts will be reflected in the
   * provided cacheable metadata.
   *
   * @param string[] $keys
   *   The cache keys of the data to store.
   * @param \Drupal\Core\Cache\CacheableMetadata $cacheable_metadata
   *   The cacheable metadata of the data to store.
   *
   * @return string
   *   The cache ID.
   */
  protected function createCacheId(array $keys, CacheableMetadata &$cacheable_metadata) {
    if ($contexts = $cacheable_metadata->getCacheContexts()) {
      $context_cache_keys = $this->cacheContextsManager->convertTokensToKeys($contexts);
      $keys = array_merge($keys, $context_cache_keys->getKeys());
      $cacheable_metadata = $cacheable_metadata->merge($context_cache_keys);
    }
    return implode(':', $keys);
  }

  /**
   * Creates a cache ID based on cache keys and cacheable metadata.
   *
   * This is a simpler, faster version of ::createCacheID() to be used when you
   * do not care about how cache context folding affects the cache tags.
   *
   * @param string[] $keys
   *   The cache keys of the data to store.
   * @param \Drupal\Core\Cache\CacheableDependencyInterface $cacheability
   *   The cache metadata of the data to store.
   *
   * @return string
   *   The cache ID for the redirect.
   */
  protected function createCacheIdFast(array $keys, CacheableDependencyInterface $cacheability) {
    if ($contexts = $cacheability->getCacheContexts()) {
      $context_cache_keys = $this->cacheContextsManager->convertTokensToKeys($contexts);
      $keys = array_merge($keys, $context_cache_keys->getKeys());
    }
    return implode(':', $keys);
  }

}
