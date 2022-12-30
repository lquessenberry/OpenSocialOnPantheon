<?php

namespace Drupal\group\Plugin;

use Drupal\Core\Extension\ModuleHandlerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a base class for group content handlers.
 *
 * @ingroup group
 */
abstract class GroupContentHandlerBase implements GroupContentHandlerInterface {

  /**
   * The group content enabler definition.
   *
   * @var array
   */
  protected $definition;

  /**
   * The plugin ID as read from the definition.
   *
   * @var string
   */
  protected $pluginId;

  /**
   * The module handler to invoke hooks on.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * Constructs a GroupContentHandlerBase object.
   *
   * @param string $plugin_id
   *   The plugin ID.
   * @param array $definition
   *   The group content enabler definition.
   */
  public function __construct($plugin_id, array $definition) {
    $this->pluginId = $plugin_id;
    $this->definition = $definition;
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, $plugin_id, array $definition) {
    return new static(
      $plugin_id,
      $definition
    );
  }

  /**
   * Gets the module handler.
   *
   * @return \Drupal\Core\Extension\ModuleHandlerInterface
   *   The module handler.
   */
  protected function moduleHandler() {
    if (!$this->moduleHandler) {
      $this->moduleHandler = \Drupal::moduleHandler();
    }
    return $this->moduleHandler;
  }

  /**
   * Sets the module handler for this handler.
   *
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   *
   * @return $this
   */
  public function setModuleHandler(ModuleHandlerInterface $module_handler) {
    $this->moduleHandler = $module_handler;
    return $this;
  }

}
