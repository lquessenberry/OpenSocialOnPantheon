<?php

namespace Drupal\votingapi;

use Drupal\Core\Entity\EntityStorageInterface;

/**
 * Defines an interface for vote entity storage classes.
 */
interface VoteResultStorageInterface extends EntityStorageInterface {

  /**
   * This function gives back the number of votes for a particular entit with a
   * particular type of voting. For example it can be used to get number of
   * likes and also dislikes. Just need to change the type.
   *
   * @param string $entity_type_id
   * @param int $entity_id the node id of the node for which number of votes is
   *   requited.
   * @param string $vote_type Plugin implementing
   *   Drupal\votingapi\Plugin\VoteType
   * @param string $function
   *
   * @return \Drupal\votingapi\Entity\VoteResult[]
   */
  public function getEntityResults($entity_type_id, $entity_id, $vote_type, $function);
}
