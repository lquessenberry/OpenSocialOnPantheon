<?php

namespace Drupal\private_message\Entity;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\user\EntityOwnerInterface;

/**
 * Provides an interface defining a Private Message Thread Access entity.
 *
 * @ingroup private_message
 */
interface PrivateMessageThreadAccessTimeInterface extends ContentEntityInterface, EntityOwnerInterface {

  /**
   * Set the time of the last access.
   *
   * @param int $timestamp
   *   The UNIX timestamp at which the private message thread that
   *   references this entity was last accessed by the given user.
   */
  public function setAccessTime($timestamp);

  /**
   * Get the time of the last access of the thread that references this entity.
   */
  public function getAccessTime();

}
