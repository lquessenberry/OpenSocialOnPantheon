<?php

namespace Drupal\votingapi;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\user\EntityOwnerInterface;
use Drupal\user\UserInterface;

/**
 * Provides an interface defining a vote entity.
 */
interface VoteResultInterface extends ContentEntityInterface, EntityOwnerInterface {

  /**
   * Returns the type of entity that the vote was cast on.
   *
   * @return string
   *   The entity type.
   */
  public function getVotedEntityType();

  /**
   * {@inheritdoc}
   */
  public function setVotedEntityType($name);

  /**
   * Returns the ID of the entity that the vote was cast on.
   *
   * @return int
   *   The entity ID.
   */
  public function getVotedEntityId();

  /**
   * {@inheritdoc}
   */
  public function setVotedEntityId($id);

  /**
   * Returns the vote value.
   *
   * @return int
   *   The numeric value of the vote.
   */
  public function getValue();

  /**
   * Sets the vote value.
   *
   * @param float $value
   *   The vote value.
   *
   * @return \Drupal\votingapi\VoteInterface
   *   The called vote entity.
   */
  public function setValue($value);

  /**
   * {@inheritdoc}
   */
  public function getValueType();

  /**
   * {@inheritdoc}
   */
  public function setValueType($value_type);

  /**
   * {@inheritdoc}
   */
  public function getOwner();

  /**
   * {@inheritdoc}
   */
  public function setOwner(UserInterface $account);

  /**
   * {@inheritdoc}
   */
  public function getOwnerId();

  /**
   * {@inheritdoc}
   */
  public function setOwnerId($uid);

  /**
   * {@inheritdoc}
   */
  public function getFunction();

  /**
   * {@inheritdoc}
   */
  public function setFunction($function);
}
