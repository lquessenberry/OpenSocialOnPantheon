<?php

namespace Drupal\votingapi\Plugin\migrate\source\d7;

use Drupal\migrate_drupal\Plugin\migrate\source\DrupalSqlBase;

/**
 * Drupal 7 vote source from database.
 *
 * @MigrateSource(
 *   id = "d7_vote",
 *   source_module = "votingapi"
 * )
 */
class Vote extends DrupalSqlBase {

  /**
   * {@inheritdoc}
   */
  public function query() {
    $query = $this->select('votingapi_vote', 'v')
      ->fields('v');
    foreach (['entity_type', 'value_type', 'tag'] as $db_field_name) {
      if (!empty($this->configuration[$db_field_name])) {
        $value = (array) $this->configuration[$db_field_name];
        $query->condition("v.$db_field_name", $value, 'IN');
      }
    }
    return $query;
  }

  /**
   * {@inheritdoc}
   */
  public function fields() {
    $fields = [
      'vote_id' => $this->t('Vote ID'),
      'entity_type' => $this->t('Entity Type'),
      'entity_id' => $this->t('Entity ID'),
      'value' => $this->t('Value'),
      'value_type' => $this->t('Value Type'),
      'tag' => $this->t('Tag'),
      'uid' => $this->t('User ID'),
      'timestamp' => $this->t('Timestamp'),
      'vote_source' => $this->t('Vote Source'),
    ];
    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function getIds() {
    $ids['vote_id']['type'] = 'integer';
    $ids['vote_id']['alias'] = 'v';
    return $ids;
  }

}
