<?php

namespace Drupal\typed_data\Widget;

use Drupal\Component\Plugin\PluginManagerInterface;

/**
 * Interface for the form widget manager.
 *
 * @see \Drupal\typed_data\Widget\FormWidgetInterface
 */
interface FormWidgetManagerInterface extends PluginManagerInterface {

  /**
   * Creates a form widget plugin instance.
   *
   * @param string $plugin_id
   *   The ID of the plugin being instantiated; i.e., the filter machine name.
   * @param array $configuration
   *   An array of configuration relevant to the plugin instance. As this plugin
   *   is not configurable, this is unused and should stay empty.
   *
   * @return \Drupal\typed_data\Widget\FormWidgetInterface
   *   A fully configured plugin instance.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   *   If the instance cannot be created, such as if the ID is invalid.
   */
  public function createInstance($plugin_id, array $configuration = []);

}
