<?php

namespace Drupal\search_api_db\DatabaseCompatibility;

use Drupal\Core\Database\DatabaseException;
use Drupal\Core\Database\Query\SelectInterface;
use Drupal\search_api\SearchApiException;

/**
 * Represents a MySQL-based database.
 */
class MySql extends GenericDatabase {

  /**
   * {@inheritdoc}
   */
  public function alterNewTable($table, $type = 'text') {
    // The Drupal MySQL integration defaults to using a 4-byte-per-character
    // encoding, which would make it impossible to use our normal 255 characters
    // long varchar fields in a primary key (since that would exceed the key's
    // maximum size). Therefore, we have to convert all tables to the "utf8"
    // character set – but we only want to make fulltext tables case-sensitive.
    $charset = $type === 'text' ? 'utf8mb4' : 'utf8';
    $collation = $type === 'text' ? 'utf8mb4_bin' : 'utf8_general_ci';
    try {
      $this->database->query("ALTER TABLE {{$table}} CONVERT TO CHARACTER SET '$charset' COLLATE '$collation'");
      // Even for text tables, we need the "item_id" column to have the same
      // collation as everywhere else. Otherwise, this can slow down search
      // queries significantly.
      if ($type === 'text') {
        $this->database->query("ALTER TABLE {{$table}} MODIFY item_id VARCHAR(150) CHARACTER SET 'utf8' COLLATE 'utf8_general_ci'");
      }
    }
    catch (\PDOException $e) {
      $class = get_class($e);
      $message = $e->getMessage();
      throw new SearchApiException("$class while trying to change collation of $type search data table '$table': $message", 0, $e);
    }
    catch (DatabaseException $e) {
      $class = get_class($e);
      $message = $e->getMessage();
      throw new SearchApiException("$class while trying to change collation of $type search data table '$table': $message", 0, $e);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function preprocessIndexValue($value, $type = 'text') {
    $value = parent::preprocessIndexValue($value, $type);
    // As MySQL removes trailing whitespace when computing primary keys, we need
    // to do the same or pseudo-duplicates could cause an exception ("Integrity
    // constraint violation: Duplicate entry") during indexing.
    if ($type !== 'text') {
      $value = rtrim($value);
    }
    return $value;
  }

  /**
   * {@inheritdoc}
   */
  public function orderByRandom(SelectInterface $query) {
    $alias = $query->addExpression('rand()', 'random_order_field');
    $query->orderBy($alias);
  }

}
