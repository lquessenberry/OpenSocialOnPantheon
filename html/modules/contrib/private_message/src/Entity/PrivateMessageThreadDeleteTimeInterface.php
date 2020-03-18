<?php

namespace Drupal\private_message\Entity;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\user\EntityOwnerInterface;

/**
 * Provides an interface defining a Private Message Thread Delation entity.
 *
 * @ingroup private_message
 */
interface PrivateMessageThreadDeleteTimeInterface extends ContentEntityInterface, EntityOwnerInterface {

  /**
   * Set the delete time for the thread that references this entity.
   *
   * The delete time is unique for each member of the thread. If all members of
   * the thread mark the thread as deleted, then the thread is hard deleted.
   *
   * @param int $timestamp
   *   The Unix timestamp at which the thread was marked as deleted.
   */
  public function setDeleteTime($timestamp);

  /**
   * Get the time that the owner of this entity marked the thread as deleted.
   */
  public function getDeleteTime();

}
