<?php

namespace Drupal\profile\Entity;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\user\EntityOwnerInterface;

/**
 * Provides an interface for profiles.
 */
interface ProfileInterface extends ContentEntityInterface, EntityChangedInterface, EntityOwnerInterface {

  /**
   * Gets whether the profile is active.
   *
   * Unpublished profiles are only visible to their authors and administrators.
   *
   * @return bool
   *   TRUE if the profile is active, FALSE otherwise.
   */
  public function isActive();

  /**
   * Sets whether the profile is active.
   *
   * @param bool $active
   *   Whether the profile is active.
   *
   * @return $this
   */
  public function setActive($active);

  /**
   * Gets whether this is the user's default profile.
   *
   * A user can have a default profile of each type.
   *
   * @return bool
   *   TRUE if this is the user's default profile, FALSE otherwise.
   */
  public function isDefault();

  /**
   * Sets whether this is the user's default profile.
   *
   * @param bool $is_default
   *   Whether this is the user's default profile.
   *
   * @return $this
   */
  public function setDefault($is_default);

  /**
   * Gets the profile creation timestamp.
   *
   * @return int
   *   The profile creation timestamp.
   */
  public function getCreatedTime();

  /**
   * Sets the profile creation timestamp.
   *
   * @param int $timestamp
   *   The profile creation timestamp.
   *
   * @return $this
   */
  public function setCreatedTime($timestamp);

  /**
   * Gets the profile revision creation timestamp.
   *
   * @return int
   *   The UNIX timestamp of when this revision was created.
   */
  public function getRevisionCreationTime();

  /**
   * Sets the profile revision creation timestamp.
   *
   * @param int $timestamp
   *   The UNIX timestamp of when this revision was created.
   *
   * @return $this
   */
  public function setRevisionCreationTime($timestamp);

  /**
   * Gets the profile revision author.
   *
   * @return \Drupal\user\UserInterface
   *   The user entity for the revision author.
   */
  public function getRevisionAuthor();

  /**
   * Sets the profile revision author.
   *
   * @param int $uid
   *   The user ID of the revision author.
   *
   * @return $this
   */
  public function setRevisionAuthorId($uid);

}
