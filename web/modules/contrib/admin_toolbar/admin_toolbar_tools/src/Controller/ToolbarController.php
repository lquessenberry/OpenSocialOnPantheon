<?php

namespace Drupal\admin_toolbar_tools\Controller;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\CronInterface;
use Drupal\Core\Menu\ContextualLinkManager;
use Drupal\Core\Menu\LocalActionManager;
use Drupal\Core\Menu\LocalTaskManager;
use Drupal\Core\Menu\MenuLinkManagerInterface;
use Drupal\Core\Plugin\CachedDiscoveryClearerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Drupal\Core\Template\TwigEnvironment;
use Drupal\Core\Theme\Registry;

/**
 * Controller for AdminToolbar Tools.
 *
 * @package Drupal\admin_toolbar_tools\Controller
 */
class ToolbarController extends ControllerBase {

  /**
   * A cron instance.
   *
   * @var \Drupal\Core\CronInterface
   */
  protected $cron;

  /**
   * A menu link manager instance.
   *
   * @var \Drupal\Core\Menu\MenuLinkManagerInterface
   */
  protected $menuLinkManager;

  /**
   * A context link manager instance.
   *
   * @var \Drupal\Core\Menu\ContextualLinkManager
   */
  protected $contextualLinkManager;

  /**
   * A local task manager instance.
   *
   * @var \Drupal\Core\Menu\LocalTaskManager
   */
  protected $localTaskLinkManager;

  /**
   * A local action manager instance.
   *
   * @var \Drupal\Core\Menu\LocalActionManager
   */
  protected $localActionLinkManager;

  /**
   * A cache backend interface instance.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected $cacheRender;

  /**
   * A date time instance.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * A request stack symfony instance.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * A plugin cache clear instance.
   *
   * @var \Drupal\Core\Plugin\CachedDiscoveryClearerInterface
   */
  protected $pluginCacheClearer;

  /**
   * The cache menu instance.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected $cacheMenu;

  /**
   * A TwigEnvironment instance.
   *
   * @var \Drupal\Core\Template\TwigEnvironment
   */
  protected $twig;

  /**
   * The search theme.registry service.
   *
   * @var \Drupal\Core\Theme\Registry
   */
  protected $themeRegistry;

  /**
   * Constructs a ToolbarController object.
   *
   * @param \Drupal\Core\CronInterface $cron
   *   A cron instance.
   * @param \Drupal\Core\Menu\MenuLinkManagerInterface $menuLinkManager
   *   A menu link manager instance.
   * @param \Drupal\Core\Menu\ContextualLinkManager $contextualLinkManager
   *   A context link manager instance.
   * @param \Drupal\Core\Menu\LocalTaskManager $localTaskLinkManager
   *   A local task manager instance.
   * @param \Drupal\Core\Menu\LocalActionManager $localActionLinkManager
   *   A local action manager instance.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cacheRender
   *   A cache backend interface instance.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   A date time instance.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   A request stack symfony instance.
   * @param \Drupal\Core\Plugin\CachedDiscoveryClearerInterface $plugin_cache_clearer
   *   A plugin cache clear instance.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_menu
   *   A cache menu instance.
   * @param \Drupal\Core\Template\TwigEnvironment $twig
   *   A TwigEnvironment instance.
   * @param \Drupal\Core\Theme\Registry $theme_registry
   *   The theme.registry service.
   */
  public function __construct(
    CronInterface $cron,
    MenuLinkManagerInterface $menuLinkManager,
    ContextualLinkManager $contextualLinkManager,
    LocalTaskManager $localTaskLinkManager,
    LocalActionManager $localActionLinkManager,
    CacheBackendInterface $cacheRender,
    TimeInterface $time,
    RequestStack $request_stack,
    CachedDiscoveryClearerInterface $plugin_cache_clearer,
    CacheBackendInterface $cache_menu,
    TwigEnvironment $twig,
    Registry $theme_registry
  ) {
    $this->cron = $cron;
    $this->menuLinkManager = $menuLinkManager;
    $this->contextualLinkManager = $contextualLinkManager;
    $this->localTaskLinkManager = $localTaskLinkManager;
    $this->localActionLinkManager = $localActionLinkManager;
    $this->cacheRender = $cacheRender;
    $this->time = $time;
    $this->requestStack = $request_stack;
    $this->pluginCacheClearer = $plugin_cache_clearer;
    $this->cacheMenu = $cache_menu;
    $this->twig = $twig;
    $this->themeRegistry = $theme_registry;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('cron'),
      $container->get('plugin.manager.menu.link'),
      $container->get('plugin.manager.menu.contextual_link'),
      $container->get('plugin.manager.menu.local_task'),
      $container->get('plugin.manager.menu.local_action'),
      $container->get('cache.render'),
      $container->get('datetime.time'),
      $container->get('request_stack'),
      $container->get('plugin.cache_clearer'),
      $container->get('cache.menu'),
      $container->get('twig'),
      $container->get('theme.registry')
    );
  }

  /**
   * Reload the previous page.
   */
  public function reloadPage() {
    $request = $this->requestStack->getCurrentRequest();
    if ($request->server->get('HTTP_REFERER')) {
      return $request->server->get('HTTP_REFERER');
    }
    else {
      return base_path();
    }
  }

  /**
   * Flushes all caches.
   */
  public function flushAll() {
    $this->messenger()->addMessage($this->t('All caches cleared.'));
    drupal_flush_all_caches();
    return new RedirectResponse($this->reloadPage());
  }

  /**
   * Flushes css and javascript caches.
   */
  public function flushJsCss() {
    $this->state()
      ->set('system.css_js_query_string', base_convert($this->time->getCurrentTime(), 10, 36));
    $this->messenger()->addMessage($this->t('CSS and JavaScript cache cleared.'));
    return new RedirectResponse($this->reloadPage());
  }

  /**
   * Flushes plugins caches.
   */
  public function flushPlugins() {
    $this->pluginCacheClearer->clearCachedDefinitions();
    $this->messenger()->addMessage($this->t('Plugins cache cleared.'));
    return new RedirectResponse($this->reloadPage());
  }

  /**
   * Resets all static caches.
   */
  public function flushStatic() {
    drupal_static_reset();
    $this->messenger()->addMessage($this->t('Static cache cleared.'));
    return new RedirectResponse($this->reloadPage());
  }

  /**
   * Clears all cached menu data.
   */
  public function flushMenu() {
    $this->cacheMenu->invalidateAll();
    $this->menuLinkManager->rebuild();
    $this->contextualLinkManager->clearCachedDefinitions();
    $this->localTaskLinkManager->clearCachedDefinitions();
    $this->localActionLinkManager->clearCachedDefinitions();
    $this->messenger()->addMessage($this->t('Routing and links cache cleared.'));
    return new RedirectResponse($this->reloadPage());
  }

  /**
   * Clears all cached views data.
   */
  public function flushViews() {
    views_invalidate_cache();
    $this->messenger()->addMessage($this->t('Views cache cleared.'));
    return new RedirectResponse($this->reloadPage());
  }

  /**
   * Clears the twig cache.
   */
  public function flushTwig() {
    $this->twig->invalidate();
    $this->messenger()->addMessage($this->t('Twig cache cleared.'));
    return new RedirectResponse($this->reloadPage());
  }

  /**
   * Run the cron.
   */
  public function runCron() {
    $this->cron->run();
    $this->messenger()->addMessage($this->t('Cron ran successfully.'));
    return new RedirectResponse($this->reloadPage());
  }

  /**
   * Clear the rendered cache.
   */
  public function cacheRender() {
    $this->cacheRender->invalidateAll();
    $this->messenger()->addMessage($this->t('Render cache cleared.'));
    return new RedirectResponse($this->reloadPage());
  }

  /**
   * Rebuild the theme registry.
   */
  public function themeRebuild() {
    $this->themeRegistry->reset();
    $this->messenger()->addMessage($this->t('Theme registry rebuilded.'));
    return new RedirectResponse($this->reloadPage());
  }

}
