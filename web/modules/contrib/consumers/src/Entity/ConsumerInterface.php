<?php

namespace Drupal\consumers\Entity;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\user\EntityOwnerInterface;

/**
 * Provides an interface defining a consumer entity.
 */
interface ConsumerInterface extends ContentEntityInterface, EntityOwnerInterface {

  /**
   * Gets the client ID.
   *
   * @return string
   *   The client ID.
   */
  public function getClientId(): string;

}
