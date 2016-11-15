<?php

namespace Drupal\flag;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\flag\Event\FlagEvents;
use Drupal\flag\Event\FlaggingEvent;
use Drupal\flag\Event\UnflaggingEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Class FlagCountManager.
 */
class FlagCountManager implements FlagCountManagerInterface, EventSubscriberInterface {

  /**
   * Stores flag counts per entity.
   *
   * @var array
   */
  protected $entityCounts = [];

  /**
   * Stores flag counts per flag.
   *
   * @var array
   */
  protected $flagCounts = [];

  /**
   * Stores flagged entity counts per flag.
   *
   * @var array
   */
  protected $flagEntityCounts = [];

  /**
   * Stores flag counts per flag and user.
   *
   * @var array
   */
  protected $userFlagCounts = [];

  /**
   * Database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $connection;

  /**
   * Constructs a FlagCountManager.
   */
  public function __construct(Connection $connection) {
    $this->connection = $connection;
  }

  /**
   * {@inheritdoc}
   */
  public function getEntityFlagCounts(EntityInterface $entity) {
    $entity_type = $entity->getEntityTypeId();
    $entity_id = $entity->id();
    if (!isset($this->entityCounts[$entity_type][$entity_id])) {
      $this->entityCounts[$entity_type][$entity_id] = [];
      $query = $this->connection->select('flag_counts', 'fc');
      $result = $query
        ->fields('fc', ['flag_id', 'count'])
        ->condition('fc.entity_type', $entity_type)
        ->condition('fc.entity_id', $entity_id)
        ->execute();
      foreach ($result as $row) {
        $this->entityCounts[$entity_type][$entity_id][$row->flag_id] = $row->count;
      }
    }

    return $this->entityCounts[$entity_type][$entity_id];
  }

  /**
   * {@inheritdoc}
   */
  public function getFlagFlaggingCount(FlagInterface $flag) {
    $flag_id = $flag->id();
    $entity_type = $flag->getFlaggableEntityTypeId();

    // We check to see if the flag count is already in the cache,
    // if it's not, run the query.
    if (!isset($this->flagCounts[$flag_id][$entity_type])) {
      $result = $this->connection->select('flagging', 'f')
        ->fields('f', ['flag_id'])
        ->condition('flag_id', $flag_id)
        ->condition('entity_type', $entity_type)
        ->countQuery()
        ->execute()
        ->fetchField();
      $this->flagCounts[$flag_id][$entity_type] = $result;
    }

    return $this->flagCounts[$flag_id][$entity_type];
  }

  /**
   * {@inheritdoc}
   */
  public function getFlagEntityCount(FlagInterface $flag) {
    $flag_id = $flag->id();

    if (!isset($this->flagEntityCounts[$flag_id])) {
      $this->flagEntityCounts[$flag_id] = $this->connection->select('flag_counts', 'fc')
        ->fields('fc', array('flag_id'))
        ->condition('flag_id', $flag_id)
        ->countQuery()
        ->execute()
        ->fetchField();
    }

    return $this->flagEntityCounts[$flag_id];
  }

  /**
   * {@inheritdoc}
   */
  public function getUserFlagFlaggingCount(FlagInterface $flag, AccountInterface $user) {
    $flag_id = $flag->id();
    $uid = $user->id();

    // We check to see if the flag count is already in the cache,
    // if it's not, run the query.
    if (!isset($this->userFlagCounts[$flag_id][$uid])) {
      $result = $this->connection->select('flagging', 'f')
        ->fields('f', ['flag_id'])
        ->condition('flag_id', $flag_id)
        ->condition('uid', $uid)
        ->countQuery()
        ->execute()
        ->fetchField();
      $this->userFlagCounts[$flag_id][$uid] = $result;
    }

    return $this->userFlagCounts[$flag_id][$uid];
  }

  /**
   * Increments count of flagged entities.
   *
   * @param \Drupal\flag\Event\FlaggingEvent $event
   *   The flagging event.
   */
  public function incrementFlagCounts(FlaggingEvent $event) {
    $flagging = $event->getFlagging();
    $flag = $flagging->getFlag();
    $entity = $flagging->getFlaggable();

    $this->connection->merge('flag_counts')
      ->key([
        'flag_id' => $flag->id(),
        'entity_id' => $entity->id(),
        'entity_type' => $entity->getEntityTypeId(),
      ])
      ->fields([
        'last_updated' => REQUEST_TIME,
        'count' => 1,
      ])
      ->expression('count', 'count + :inc', [':inc' => 1])
      ->execute();

    $this->resetLoadedCounts($entity, $flag);
  }

  /**
   * Decrements count of flagged entities.
   *
   * @param \Drupal\flag\Event\UnflaggingEvent $event
   *   The unflagging event.
   */
  public function decrementFlagCounts(UnflaggingEvent $event) {

    $flaggings_count = [];
    $flag_ids = [];
    $entity_ids = [];

    $flaggings = $event->getFlaggings();

    // Attempt to optimize the amount of queries that need to be executed if
    // a lot of flaggings are deleted. Build a list of flags and entity_ids
    // that will need to be updated. Entity type is ignored since one flag is
    // specific to a given entity type.
    foreach ($flaggings as $flagging) {
      $flag_id = $flagging->getFlagId();
      $entity_id = $flagging->getFlaggableId();

      $flag_ids[$flag_id] = $flag_id;
      $entity_ids[$entity_id] = $entity_id;
      if (!isset($flaggings_count[$flag_id][$entity_id])) {
        $flaggings_count[$flag_id][$entity_id] = 1;
      }
      else {
        $flaggings_count[$flag_id][$entity_id]++;
      }

      $this->resetLoadedCounts($flagging->getFlaggable(), $flagging->getFlag());
    }

    // Build a query that fetches the count for all flag and entity ID
    // combinations.
    $result = $this->connection->select('flag_counts')
      ->fields('flag_counts', ['flag_id', 'entity_type', 'entity_id', 'count'])
      ->condition('flag_id', $flag_ids, 'IN')
      ->condition('entity_id', $entity_ids, 'IN')
      ->execute();

    $to_delete = [];
    foreach ($result as $row) {
      // The query above could fetch combinations that are not being deleted
      // skip them now.
      // Most cases will either delete flaggings of a single flag or a single
      // entity where that does not happen.
      if (!isset($flaggings_count[$row->flag_id][$row->entity_id])) {
        continue;
      }

      if ($row->count <= $flaggings_count[$row->flag_id][$row->entity_id]) {
        // If all flaggings for the given flag and entity are deleted, delete
        // the row.
        $to_delete[$row->flag_id][] = $row->entity_id;
      }
      else {
        // Otherwise, update the count.
        $this->connection->update('flag_counts')
          ->expression('count', 'count - :decrement', [':decrement' => $flaggings_count[$row->flag_id][$row->entity_id]])
          ->condition('flag_id', $row->flag_id)
          ->condition('entity_id', $row->entity_id)
          ->execute();
      }
    }

    // Execute a delete query per flag.
    foreach ($to_delete as $flag_id => $entity_ids) {
      $this->connection->delete('flag_counts')
        ->condition('flag_id', $flag_id)
        ->condition('entity_id', $entity_ids, 'IN')
        ->execute();
    }

  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events = array();
    $events[FlagEvents::ENTITY_FLAGGED][] = array('incrementFlagCounts', -100);
    $events[FlagEvents::ENTITY_UNFLAGGED][] = array(
      'decrementFlagCounts',
      -100
    );
    return $events;
  }

  /**
   * Resets loaded flag counts.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The flagged entity.
   * @param \Drupal\flag\FlagInterface $flag
   *   The flag.
   */
  protected function resetLoadedCounts(EntityInterface $entity, FlagInterface $flag) {
    // @todo Consider updating them instead of just clearing it.
    unset($this->entityCounts[$entity->getEntityTypeId()][$entity->id()]);
    unset($this->flagCounts[$flag->id()]);
    unset($this->flagEntityCounts[$flag->id()]);
    unset($this->userFlagCounts[$flag->id()]);
  }

}
