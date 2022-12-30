<?php

namespace Drupal\group\Access;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\AccountSwitcherInterface;
use Drupal\variationcache\Cache\VariationCacheInterface;

/**
 * Collects group permissions for an account.
 */
class ChainGroupPermissionCalculator implements ChainGroupPermissionCalculatorInterface {

  /**
   * The calculators.
   *
   * @var \Drupal\group\Access\GroupPermissionCalculatorInterface[]
   */
  protected $calculators = [];

  /**
   * The variation cache backend to use as a persistent cache.
   *
   * @var \Drupal\variationcache\Cache\VariationCacheInterface
   */
  protected $cache;

  /**
   * The variation cache backend to use as a static cache.
   *
   * @var \Drupal\variationcache\Cache\VariationCacheInterface
   */
  protected $static;

  /**
   * The regular cache backend to use as a static cache.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected $regularStatic;

  /**
   * The account switcher service.
   *
   * @var \Drupal\Core\Session\AccountSwitcherInterface
   */
  protected $accountSwitcher;

  /**
   * Constructs a ChainGroupPermissionCalculator object.
   *
   * @param \Drupal\variationcache\Cache\VariationCacheInterface $cache
   *   The variation cache to use as a persistent cache.
   * @param \Drupal\variationcache\Cache\VariationCacheInterface $static
   *   The variation cache to use as a static cache.
   * @param \Drupal\Core\Cache\CacheBackendInterface $regular_static
   *   The regular cache backend to use as a static cache.
   * @param \Drupal\Core\Session\AccountSwitcherInterface $account_switcher
   *   The account switcher service.
   */
  public function __construct(VariationCacheInterface $cache, VariationCacheInterface $static, CacheBackendInterface $regular_static, AccountSwitcherInterface $account_switcher) {
    $this->cache = $cache;
    $this->static = $static;
    $this->regularStatic = $regular_static;
    $this->accountSwitcher = $account_switcher;
  }

  /**
   * {@inheritdoc}
   */
  public function addCalculator(GroupPermissionCalculatorInterface $calculator) {
    $this->calculators[] = $calculator;
  }

  /**
   * {@inheritdoc}
   */
  public function getCalculators() {
    return $this->calculators;
  }

  /**
   * Performs the calculation of permissions with caching support.
   *
   * @param string[] $cache_keys
   *   The cache keys to store the calculation with.
   * @param string[] $persistent_cache_contexts
   *   The cache contexts that are always used for this calculation.
   * @param string $method
   *   The method to invoke on each calculator.
   * @param array $args
   *   The arguments to pass to the calculator method.
   *
   * @return \Drupal\group\Access\CalculatedGroupPermissionsInterface
   *   The calculated group permissions, potentially served from a cache.
   */
  protected function doCacheableCalculation(array $cache_keys, array $persistent_cache_contexts, $method, array $args = []) {
    $initial_cacheability = (new CacheableMetadata())->addCacheContexts($persistent_cache_contexts);

    // Whether to switch the user account during cache storage and retrieval.
    //
    // This is necessary because permissions may be stored varying by the user
    // cache context or one of its child contexts. Because we may be calculating
    // permissions for an account other than the current user, we need to ensure
    // that the cache ID for said entry is set according to the passed in
    // account's data.
    //
    // Drupal core does not help us here because there is no way to reuse the
    // cache context logic outside of the caching layer. This means that in
    // order to generate a cache ID based on, let's say, one's permissions, we'd
    // have to copy all of the permission hash generation logic. Same goes for
    // the optimizing/folding of cache contexts.
    //
    // Instead of doing so, we simply set the current user to the passed in
    // account, calculate the cache ID and then immediately switch back. It's
    // the cleanest solution we could come up with that doesn't involve copying
    // half of core's caching layer and that still allows us to use the
    // VariationCache for accounts other than the current user.
    $switch_account = FALSE;
    foreach ($persistent_cache_contexts as $cache_context) {
      list($cache_context_root) = explode('.', $cache_context, 2);
      if ($cache_context_root === 'user') {
        $switch_account = TRUE;
        $this->accountSwitcher->switchTo($args[0]);
        break;
      }
    }

    // Retrieve the permissions from the static cache if available.
    $static_cache_hit = FALSE;
    $persistent_cache_hit = FALSE;
    if ($static_cache = $this->static->get($cache_keys, $initial_cacheability)) {
      $static_cache_hit = TRUE;
      $calculated_permissions = $static_cache->data;
    }
    // Retrieve the permissions from the persistent cache if available.
    elseif ($cache = $this->cache->get($cache_keys, $initial_cacheability)) {
      $persistent_cache_hit = TRUE;
      $calculated_permissions = $cache->data;
    }
    // Otherwise build the permissions and store them in the persistent cache.
    else {
      $calculated_permissions = new RefinableCalculatedGroupPermissions();
      foreach ($this->getCalculators() as $calculator) {
        $calculated_permissions = $calculated_permissions->merge(call_user_func_array([$calculator, $method], $args));
      }

      // Apply a cache tag to easily flush the calculated group permissions.
      $calculated_permissions->addCacheTags(['group_permissions']);

      // Cache the permissions as an immutable value object.
      $calculated_permissions = new CalculatedGroupPermissions($calculated_permissions);
    }

    // The persistent cache contexts are only used internally and should never
    // bubble up. We therefore only add them to the cacheable metadata provided
    // to the VariationCache, but not the actual object we're storing.
    if (!$static_cache_hit) {
      $final_cacheability = CacheableMetadata::createFromObject($calculated_permissions)->addCacheContexts($persistent_cache_contexts);
      $this->static->set($cache_keys, $calculated_permissions, $final_cacheability, $initial_cacheability);
      if (!$persistent_cache_hit) {
        $this->cache->set($cache_keys, $calculated_permissions, $final_cacheability, $initial_cacheability);
      }
    }

    if ($switch_account) {
      $this->accountSwitcher->switchBack();
    }

    return $calculated_permissions;
  }

  /**
   * {@inheritdoc}
   */
  public function calculateAnonymousPermissions() {
    return $this->doCacheableCalculation(
      ['group_permissions', 'anonymous'],
      $this->getPersistentAnonymousCacheContexts(),
      __FUNCTION__
    );
  }

  /**
   * {@inheritdoc}
   */
  public function calculateOutsiderPermissions(AccountInterface $account) {
    return $this->doCacheableCalculation(
      ['group_permissions', 'outsider'],
      $this->getPersistentOutsiderCacheContexts(),
      __FUNCTION__,
      [$account]
    );
  }

  /**
   * {@inheritdoc}
   */
  public function calculateMemberPermissions(AccountInterface $account) {
    return $this->doCacheableCalculation(
      ['group_permissions', 'member'],
      $this->getPersistentMemberCacheContexts(),
      __FUNCTION__,
      [$account]
    );
  }

  /**
   * Performs the retrieval of persistent cache contexts.
   *
   * @param string $constant_name
   *   The constant to read from each calculator.
   *
   * @return string[]
   *   The combined persistent cache contexts from all calculators.
   */
  protected function getPersistentCacheContexts($constant_name) {
    $cid = 'group_permission:chain_calculator:contexts:' . $constant_name;

    // Retrieve the contexts from the regular static cache if available.
    if ($static_cache = $this->regularStatic->get($cid)) {
      $contexts = $static_cache->data;
    }
    else {
      $contexts = [];
      foreach ($this->getCalculators() as $calculator) {
        $contexts = array_merge($contexts, constant(get_class($calculator) . '::' . $constant_name));
      }

      // Store the contexts in the regular static cache.
      $this->regularStatic->set($cid, $contexts);
    }

    return $contexts;
  }

  /**
   * Gets the cache contexts that always apply to the anonymous permissions.
   *
   * @return string[]
   */
  protected function getPersistentAnonymousCacheContexts() {
    return $this->getPersistentCacheContexts('ANONYMOUS_CACHE_CONTEXTS');
  }

  /**
   * Gets the cache contexts that always apply to the outsider permissions.
   *
   * @return string[]
   */
  protected function getPersistentOutsiderCacheContexts() {
    return $this->getPersistentCacheContexts('OUTSIDER_CACHE_CONTEXTS');
  }

  /**
   * Gets the cache contexts that always apply to the member permissions.
   *
   * @return string[]
   */
  protected function getPersistentMemberCacheContexts() {
    return $this->getPersistentCacheContexts('MEMBER_CACHE_CONTEXTS');
  }

  /**
   * {@inheritdoc}
   */
  public function calculateAuthenticatedPermissions(AccountInterface $account) {
    $calculated_permissions = new RefinableCalculatedGroupPermissions();
    $calculated_permissions
      ->merge($this->calculateOutsiderPermissions($account))
      ->merge($this->calculateMemberPermissions($account));
    return new CalculatedGroupPermissions($calculated_permissions);
  }

  /**
   * {@inheritdoc}
   */
  public function calculatePermissions(AccountInterface $account) {
    return $account->isAnonymous()
      ? $this->calculateAnonymousPermissions()
      : $this->calculateAuthenticatedPermissions($account);
  }

}
