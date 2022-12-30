<?php

/**
 * @file
 * Hooks provided by the Database Search module.
 */

/**
 * @addtogroup hooks
 * @{
 */

/**
 * Preprocess a search's database query before it is executed.
 *
 * This allows other modules to alter the DB query before a count query (or
 * facet queries, or other related queries) are constructed from it.
 *
 * @param \Drupal\Core\Database\Query\SelectInterface $db_query
 *   The database query to be executed for the search. Will have "item_id" and
 *   "score" columns in its result.
 * @param \Drupal\search_api\Query\QueryInterface $query
 *   The search query that is being executed.
 *
 * @deprecated in search_api:8.x-1.16 and is removed from search_api:2.0.0.
 *   Please use the "search_api_db.query_pre_execute" event instead.
 *
 * @see https://www.drupal.org/node/3103591
 * @see \Drupal\search_api_db\Plugin\search_api\backend\Database::preQuery()
 */
function hook_search_api_db_query_alter(\Drupal\Core\Database\Query\SelectInterface &$db_query, \Drupal\search_api\Query\QueryInterface $query) {
  // If the option was set on the query, add additional SQL conditions.
  if ($custom = $query->getOption('custom_sql_conditions')) {
    foreach ($custom as $condition) {
      $db_query->condition($condition['field'], $condition['value'], $condition['operator']);
    }
  }
}

/**
 * @} End of "addtogroup hooks".
 */
