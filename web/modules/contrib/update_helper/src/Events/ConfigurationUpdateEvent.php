<?php

namespace Drupal\update_helper\Events;

use Drupal\Component\EventDispatcher\Event;

/**
 * Event for configuration update execution.
 *
 * @package Drupal\update_helper\Events
 */
class ConfigurationUpdateEvent extends Event {

  /**
   * Module name.
   *
   * @var string
   */
  protected $module;

  /**
   * Update name.
   *
   * @var string
   */
  protected $updateName;

  /**
   * Count of warnings occurred during update execution.
   *
   * @var int
   */
  protected $warningCount;

  /**
   * Configuration update event.
   *
   * @param string $module
   *   Module name.
   * @param string $updateName
   *   Update name.
   * @param int $warningCount
   *   Count of warnings occurred during update execution.
   */
  public function __construct($module, $updateName, $warningCount) {
    $this->module = $module;
    $this->updateName = $updateName;
    $this->warningCount = $warningCount;
  }

  /**
   * Get module name.
   *
   * @return string
   *   Returns module name.
   */
  public function getModule() {
    return $this->module;
  }

  /**
   * Get update name.
   *
   * @return string
   *   Returns update name.
   */
  public function getUpdateName() {
    return $this->updateName;
  }

  /**
   * Get status for configuration update.
   *
   * @return bool
   *   Returns status for configuration update.
   */
  public function isSuccessful() {
    return $this->warningCount === 0;
  }

}
