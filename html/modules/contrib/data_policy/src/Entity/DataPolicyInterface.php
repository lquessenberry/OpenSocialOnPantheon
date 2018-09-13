<?php

namespace Drupal\data_policy\Entity;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\RevisionLogInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\user\EntityOwnerInterface;

/**
 * Provides an interface for defining Data policy entities.
 *
 * @ingroup data_policy
 */
interface DataPolicyInterface extends ContentEntityInterface, RevisionLogInterface, EntityChangedInterface, EntityOwnerInterface {

  /**
   * Gets the Data policy name.
   *
   * @return string
   *   Name of the Data policy.
   */
  public function getName();

  /**
   * Sets the Data policy name.
   *
   * @param string $name
   *   The Data policy name.
   *
   * @return \Drupal\data_policy\Entity\DataPolicyInterface
   *   The called Data policy entity.
   */
  public function setName($name);

  /**
   * Gets the Data policy creation timestamp.
   *
   * @return int
   *   Creation timestamp of the Data policy.
   */
  public function getCreatedTime();

  /**
   * Sets the Data policy creation timestamp.
   *
   * @param int $timestamp
   *   The Data policy creation timestamp.
   *
   * @return \Drupal\data_policy\Entity\DataPolicyInterface
   *   The called Data policy entity.
   */
  public function setCreatedTime($timestamp);

  /**
   * Returns the Data policy published status indicator.
   *
   * Unpublished Data policy are only visible to restricted users.
   *
   * @return bool
   *   TRUE if the Data policy is published.
   */
  public function isPublished();

  /**
   * Sets the published status of a Data policy.
   *
   * @param bool $published
   *   TRUE to set this Data policy to published, FALSE to set it to
   *   unpublished.
   *
   * @return \Drupal\data_policy\Entity\DataPolicyInterface
   *   The called Data policy entity.
   */
  public function setPublished($published);

  /**
   * Gets the Data policy revision creation timestamp.
   *
   * @return int
   *   The UNIX timestamp of when this revision was created.
   */
  public function getRevisionCreationTime();

  /**
   * Sets the Data policy revision creation timestamp.
   *
   * @param int $timestamp
   *   The UNIX timestamp of when this revision was created.
   *
   * @return \Drupal\data_policy\Entity\DataPolicyInterface
   *   The called Data policy entity.
   */
  public function setRevisionCreationTime($timestamp);

  /**
   * Gets the Data policy revision author.
   *
   * @return \Drupal\user\UserInterface
   *   The user entity for the revision author.
   */
  public function getRevisionUser();

  /**
   * Sets the Data policy revision author.
   *
   * @param int $uid
   *   The user ID of the revision author.
   *
   * @return \Drupal\data_policy\Entity\DataPolicyInterface
   *   The called Data policy entity.
   */
  public function setRevisionUserId($uid);

}
