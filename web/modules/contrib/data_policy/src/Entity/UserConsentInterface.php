<?php

namespace Drupal\data_policy\Entity;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\user\EntityOwnerInterface;

/**
 * Provides an interface for defining User consent entities.
 *
 * @ingroup data_policy
 */
interface UserConsentInterface extends ContentEntityInterface, EntityChangedInterface, EntityOwnerInterface {

  /**
   * The user only visits agreement page.
   */
  const STATE_UNDECIDED = 0;

  /**
   * The user submitted agreement form but do not set checkbox.
   */
  const STATE_NOT_AGREE = 1;

  /**
   * The user has given consent on the current version of data policy.
   */
  const STATE_AGREE = 2;

  /**
   * Gets the User consent name.
   *
   * @return string
   *   Name of the User consent.
   */
  public function getName();

  /**
   * Sets the User consent name.
   *
   * @param string $name
   *   The User consent name.
   *
   * @return \Drupal\data_policy\Entity\UserConsentInterface
   *   The called User consent entity.
   */
  public function setName($name);

  /**
   * Gets the User consent creation timestamp.
   *
   * @return int
   *   Creation timestamp of the User consent.
   */
  public function getCreatedTime();

  /**
   * Sets the User consent creation timestamp.
   *
   * @param int $timestamp
   *   The User consent creation timestamp.
   *
   * @return \Drupal\data_policy\Entity\UserConsentInterface
   *   The called User consent entity.
   */
  public function setCreatedTime($timestamp);

  /**
   * Returns the User consent published status indicator.
   *
   * Unpublished User consent are only visible to restricted users.
   *
   * @return bool
   *   TRUE if the User consent is published.
   */
  public function isPublished();

  /**
   * Sets the published status of a User consent.
   *
   * @param bool $published
   *   TRUE to set this User consent to published, FALSE to set it to
   *   unpublished.
   *
   * @return \Drupal\data_policy\Entity\UserConsentInterface
   *   The called User consent entity.
   */
  public function setPublished($published);

  /**
   * Sets the Data policy revision author.
   *
   * @param \Drupal\data_policy\Entity\DataPolicyInterface $data_policy
   *   The data policy entity object.
   *
   * @return \Drupal\data_policy\Entity\UserConsentInterface
   *   The called User consent entity.
   */
  public function setRevision(DataPolicyInterface $data_policy);

  /**
   * Returns the display name of user.
   *
   * @return string|null
   *   Name of user.
   */
  public function label();

}
