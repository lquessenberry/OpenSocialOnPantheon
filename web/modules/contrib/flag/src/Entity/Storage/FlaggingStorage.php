<?php

namespace Drupal\flag\Entity\Storage;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\Sql\SqlContentEntityStorage;
use Drupal\Core\Session\AccountInterface;

/**
 * Default SQL flagging storage.
 */
class FlaggingStorage extends SqlContentEntityStorage implements FlaggingStorageInterface {

  /**
   * Stores loaded flags per user, entity type and IDs.
   *
   * @var array
   */
  protected $flagIdsByEntity = [];

  /**
   * Stores global flags per entity type and IDs.
   * @var array
   */
  protected $globalFlagIdsByEntity = [];

  /**
   * {@inheritdoc}
   */
  public function loadIsFlagged(EntityInterface $entity, AccountInterface $account) {
    $flag_ids = $this->loadIsFlaggedMultiple([$entity], $account);
    return $flag_ids[$entity->id()];
  }

  /**
   * {@inheritdoc}
   */
  public function loadIsFlaggedMultiple($entities, AccountInterface $account) {
    $flag_ids_by_entity = [];

    if (!$entities) {
      return $flag_ids_by_entity;
    }

    // All entities must be of the same type, get the entity type from the
    // first.
    $entity_type_id = reset($entities)->getEntityTypeId();
    $ids_to_load = [];

    // Loop over all requested entities, if they are already in the loaded list,
    // get then from there, merge the global and per-user flags together.
    foreach ($entities as $entity) {
      if (isset($this->flagIdsByEntity[$account->id()][$entity_type_id][$entity->id()])) {
        $flag_ids_by_entity[$entity->id()] = array_merge($this->flagIdsByEntity[$account->id()][$entity_type_id][$entity->id()], $this->globalFlagIdsByEntity[$entity_type_id][$entity->id()]);
      }
      else {
        $ids_to_load[$entity->id()] = [];
      }
    }

    // If there are no entities that need to be loaded, return the list.
    if (!$ids_to_load) {
      return $flag_ids_by_entity;
    }

    // Initialize the loaded lists with the missing ID's as an empty array.
    if (!isset($this->flagIdsByEntity[$account->id()][$entity_type_id])) {
      $this->flagIdsByEntity[$account->id()][$entity_type_id] = [];
    }
    if (!isset($this->globalFlagIdsByEntity[$entity_type_id])) {
      $this->globalFlagIdsByEntity[$entity_type_id] = [];
    }
    $this->flagIdsByEntity[$account->id()][$entity_type_id] += $ids_to_load;
    $this->globalFlagIdsByEntity[$entity_type_id] += $ids_to_load;
    $flag_ids_by_entity += $ids_to_load;

    // Directly query the table to avoid he overhead of loading the content
    // entities.
    $query = $this->database->select('flagging', 'f')
      ->fields('f', ['entity_id', 'flag_id', 'global'])
      ->condition('entity_type', $entity_type_id)
      ->condition('entity_id', array_keys($ids_to_load));

    // The flagging must either match the user or be global, avoid an empty IN
    // condition if there are no global flags.
    $user_or_global_condition = $query->orConditionGroup()
      ->condition('global', 1);
    if ($account->isAnonymous()) {
      if (($session = \Drupal::request()->getSession()) && ($session_flaggings = $session->get('flaggings', []))) {
        $user_or_global_condition->condition('id', $session_flaggings, 'IN');
      }
    }
    else {
      $user_or_global_condition->condition('uid', $account->id());
    }

    $result = $query
      ->condition($user_or_global_condition)
      ->execute();

    // Loop over all results, put them in the cached list and the list that will
    // be returned.
    foreach ($result as $row) {
      if ($row->global) {
        $this->globalFlagIdsByEntity[$entity_type_id][$row->entity_id][$row->flag_id] = $row->flag_id;
      }
      else {
        $this->flagIdsByEntity[$account->id()][$entity_type_id][$row->entity_id][$row->flag_id] = $row->flag_id;
      }
      $flag_ids_by_entity[$row->entity_id][$row->flag_id] = $row->flag_id;
    }

    return $flag_ids_by_entity;
  }


}
