<?php

namespace Drupal\ultimate_cron;

use Drupal\Core\Database\Database;

/**
 * Class DatabaseFactory
 */
class UltimateCronDatabaseFactory {
  /**
   * Factory method that returns a Connection object with the correct target.
   *
   * @return \Drupal\Core\Database\Connection
   *   The connection object.
   */
  public static function getConnection() {
    $target = _ultimate_cron_get_transactional_safe_connection();
    return Database::getConnection($target);
  }

}
