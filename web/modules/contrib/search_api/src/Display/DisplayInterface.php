<?php

namespace Drupal\search_api\Display;

use Drupal\Component\Plugin\DerivativeInspectionInterface;
use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Component\Plugin\DependentPluginInterface;
use Drupal\search_api\Plugin\HideablePluginInterface;

/**
 * Defines an interface for display plugins.
 *
 * @see \Drupal\search_api\Annotation\SearchApiDisplay
 * @see \Drupal\search_api\Display\DisplayPluginManager
 * @see \Drupal\search_api\Display\DisplayPluginBase
 * @see plugin_api
 */
interface DisplayInterface extends HideablePluginInterface, PluginInspectionInterface, DerivativeInspectionInterface, ContainerFactoryPluginInterface, DependentPluginInterface {

  /**
   * Returns the display label.
   *
   * @return string
   *   A human-readable label for the display.
   */
  public function label();

  /**
   * Returns the display description.
   *
   * @return string
   *   A human-readable description for the display.
   */
  public function getDescription();

  /**
   * Returns the index used by this display.
   *
   * @return \Drupal\search_api\IndexInterface
   *   The search index used by this display.
   */
  public function getIndex();

  /**
   * Returns the URL of this display.
   *
   * @return \Drupal\Core\Url|null
   *   The URL of the display, or NULL if there is no specific URL for it.
   *
   * @deprecated in search_api:8.x-1.0-beta5 and is removed from
   *   search_api:2.0.0. Use getPath() instead.
   *
   * @see https://www.drupal.org/node/2856050
   */
  public function getUrl();

  /**
   * Returns the base path used by this display.
   *
   * @return string|null
   *   The base path for this display, or NULL if there is none.
   */
  public function getPath();

  /**
   * Returns true if the display is being rendered in the current request.
   *
   * @return bool
   *   True when the display is rendered in the current request.
   */
  public function isRenderedInCurrentRequest();

}
