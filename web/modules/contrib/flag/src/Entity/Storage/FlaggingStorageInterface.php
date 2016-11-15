<?php

namespace Drupal\flag\Entity\Storage;

use Drupal\Core\Entity\ContentEntityStorageInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Flagging storage.
 */
interface FlaggingStorageInterface extends ContentEntityStorageInterface {

  /**
   * Loads a list of flags the entity is flagged with for the given account.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to check.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user account to check for.
   *
   * @return string[]
   *   A list of flag_ids that for which the given entity is flagged, either
   *   globally or for the given account.
   *
   */
  public function loadIsFlagged(EntityInterface $entity, AccountInterface $account);

  /**
   * Loads a list of flags the entities are flagged with for the given account.
   *
   * @param \Drupal\Core\Entity\EntityInterface[] $entities
   *   The entities to check. All entities must be of the same type.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user account to check for.
   *
   * @return string[][]
   *   A list of flag_ids that for which the given entity is flagged, either
   *   globally or for the given account. Keyed by the entity IDs.
   */
  public function loadIsFlaggedMultiple($entities, AccountInterface $account);

}
