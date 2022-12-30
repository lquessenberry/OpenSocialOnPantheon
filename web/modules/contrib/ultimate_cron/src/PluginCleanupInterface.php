<?php

namespace Drupal\ultimate_cron;

/**
 * Plugins can implement this to be invoked for regular cleanup tasks.
 */
interface PluginCleanupInterface {

  /**
   * Cleans and purges data stored by this plugin.
   */
  function cleanup();

}
