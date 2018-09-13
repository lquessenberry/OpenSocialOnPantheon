<?php

namespace Drupal\social_api\Plugin;

use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;

/**
 * Defines an interface for Social Network plugins.
 */
interface NetworkInterface extends PluginInspectionInterface, ContainerFactoryPluginInterface {

  /**
   * Authenticates the request with the SDK library.
   *
   * Most of the time this will just mean settings some state properties for so
   * the publish method can pass them along to the external SDK library. The
   * authentication is considered to be at the plugin level. If your network
   * implementation needs the authentication to happen at every request,
   * implement that business logic in doPost.
   */
  public function authenticate();

  /**
   * Gets the underlying SDK library.
   *
   * @return mixed
   *   The SDK client.
   */
  public function getSdk();

}
