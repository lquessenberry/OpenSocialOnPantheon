<?php

namespace Drupal\lazy;

use Drupal\Core\Condition\ConditionManager;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ModuleHandler;
use Drupal\Core\Routing\AdminContext;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Lazy-load service.
 *
 * Enables lazy-loading.
 */
class Lazy implements LazyInterface {

  /**
   * A config object for the module configuration.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $lazySettings;

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * The condition plugin manager.
   *
   * @var \Drupal\Core\Condition\ConditionManager
   */
  protected $conditionManager;

  /**
   * The route admin context to determine whether a route is an admin one.
   *
   * @var \Drupal\Core\Routing\AdminContext
   */
  protected $adminContext;

  /**
   * The module manager.
   *
   * @var \Drupal\Core\Extension\ModuleHandler
   */
  protected $moduleHandler;

  /**
   * Lazy constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The configuration factory.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack.
   * @param \Drupal\Core\Condition\ConditionManager $condition_manager
   *   The condition plugins manager.
   * @param \Drupal\Core\Routing\AdminContext $admin_context
   *   The route admin context service.
   * @param \Drupal\Core\Extension\ModuleHandler $module_handler
   *   The module manager.
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    RequestStack $request_stack,
    ConditionManager $condition_manager,
    AdminContext $admin_context,
    ModuleHandler $module_handler
  ) {
    $this->lazySettings = $config_factory->get('lazy.settings')->get();
    $this->requestStack = $request_stack->getCurrentRequest();
    $this->conditionManager = $condition_manager;
    $this->adminContext = $admin_context;
    $this->moduleHandler = $module_handler;
  }

  /**
   * {@inheritdoc}
   */
  public function getSettings(): array {
    $this->moduleHandler->alter('lazy_settings', $this->lazySettings);
    return $this->lazySettings ?? [];
  }

  /**
   * {@inheritdoc}
   */
  public function getPlugins(): array {
    return [
      'artdirect' => 'artdirect/ls.artdirect',
      'aspectratio' => 'aspectratio/ls.aspectratio',
      'attrchange' => 'attrchange/ls.attrchange',
      'bgset' => 'bgset/ls.bgset',
      'blur-up' => 'blur-up/ls.blur-up',
      'custommedia' => 'custommedia/ls.custommedia',
      'fix-edge-h-descriptor' => 'fix-edge-h-descriptor/ls.fix-edge-h-descriptor',
      'fix-ios-sizes' => 'fix-ios-sizes/fix-ios-sizes',
      'include' => 'include/ls.include',
      'native-loading' => 'native-loading/ls.native-loading',
      'noscript' => 'noscript/ls.noscript',
      'object-fit' => 'object-fit/ls.object-fit',
      'optimumx' => 'optimumx/ls.optimumx',
      'parent-fit' => 'parent-fit/ls.parent-fit',
      'print' => 'print/ls.print',
      'progressive' => 'progressive/ls.progressive',
      'respimg' => 'respimg/ls.respimg',
      'rias' => 'rias/ls.rias',
      'static-gecko-picture' => 'static-gecko-picture/ls.static-gecko-picture',
      'twitter' => 'twitter/ls.twitter',
      'unload' => 'unload/ls.unload',
      'unveilhooks' => 'unveilhooks/ls.unveilhooks',
      'video-embed' => 'video-embed/ls.video-embed',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function isEnabled(array $attributes = []): bool {
    return $this->isPathAllowed() && $this->isNotSkipping($attributes);
  }

  /**
   * {@inheritdoc}
   */
  public function isPathAllowed(): bool {
    // Disable lazy-loading for all AMP pages.
    if ($this->requestStack->query->has('amp')) {
      return FALSE;
    }

    $settings = $this->getSettings();

    // Disable lazy-loading on all administrative pages.
    $disable_admin = !isset($settings['disable_admin']) || (bool) $settings['disable_admin'];
    if ($disable_admin && $this->adminContext->isAdminRoute()) {
      return FALSE;
    }

    /** @var \Drupal\system\Plugin\Condition\RequestPath $condition */
    $condition = $this->conditionManager->createInstance('request_path');
    if (is_null($condition)) {
      return FALSE;
    }

    $visibility = $settings['visibility'] ?? [
      'id' => 'request_path',
      'pages' => $settings['disabled_paths'] ?? '/rss.xml',
      'negate' => 0,
    ];
    $condition->setConfiguration($visibility);
    if ($condition->isNegated()) {
      return $condition->evaluate();
    }
    return !$condition->evaluate();
  }

  /**
   * Skip lazy-loading?
   *
   * Returns true if the image does not have the skip class name.
   *
   * @param array $attributes
   *   Element attributes array. i.e. `$variables['attributes']`.
   *
   * @return bool
   *   Returns true if the element is NOT skipped.
   */
  private function isNotSkipping(array $attributes = []): bool {
    $classes = $attributes['class'] ?? [];
    return !in_array($this->lazySettings['skipClass'], $classes, TRUE);
  }

}
