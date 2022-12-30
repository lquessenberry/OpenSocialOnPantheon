<?php

namespace Drupal\update_helper\Events;

/**
 * Update helper events.
 *
 * @package Drupal\update_helper\Events
 */
final class UpdateHelperEvents {

  /**
   * Event dispatched when command interact is executed.
   */
  const COMMAND_GCU_INTERACT = 'update_helper.command.gcu.interact';

  /**
   * Event dispatched when command is executed.
   */
  const COMMAND_GCU_EXECUTE = 'update_helper.command.gcu.execute';

  /**
   * Event dispatched when configuration update is executed.
   */
  const CONFIGURATION_UPDATE = 'update_helper.configuration.update';

}
