<?php

namespace Drupal\update_helper;

/**
 * Interface for the Update entity.
 */
interface UpdaterInterface {

  /**
   * Get update logger service.
   *
   * @return \Drupal\update_helper\UpdateLogger
   *   Returns update logger.
   */
  public function logger();

  /**
   * Execute update of configuration from update definitions.
   *
   * @param string $module
   *   Module name where update definition is saved.
   * @param string $update_definition_name
   *   Update definition name. Usually same name as update hook.
   */
  public function executeUpdate($module, $update_definition_name);

}
