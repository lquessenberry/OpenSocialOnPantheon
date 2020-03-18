<?php

namespace Drupal\votingapi\Entity;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\user\UserInterface;
use Drupal\votingapi\VoteInterface;

/**
 * Defines the Vote entity.
 *
 * @ingroup votingapi
 *
 * @ContentEntityType(
 *   id = "vote",
 *   label = @Translation("Vote"),
 *   bundle_label = @Translation("Vote type"),
 *   bundle_entity_type = "vote_type",
 *   handlers = {
 *     "storage" = "Drupal\votingapi\VoteStorage",
 *     "access" = "Drupal\votingapi\VoteAccessControlHandler",
 *     "views_data" = "Drupal\votingapi\Entity\VoteViewsData",
 *   },
 *   base_table = "votingapi_vote",
 *   translatable = FALSE,
 *   entity_keys = {
 *     "id" = "id",
 *     "uuid" = "uuid",
 *     "bundle" = "type",
 *   }
 * )
 */
class Vote extends ContentEntityBase implements VoteInterface {

  /**
   * {@inheritdoc}
   */
  function getVotedEntityType() {
    return $this->get('entity_type')->value;
  }

  /**
   * {@inheritdoc}
   */
  function setVotedEntityType($name) {
    return $this->set('entity_type', $name);
  }

  /**
   * {@inheritdoc}
   */
  function getVotedEntityId() {
    return $this->get('entity_id')->target_id;
  }

  /**
   * {@inheritdoc}
   */
  function setVotedEntityId($id) {
    return $this->set('entity_id', $id);
  }

  /**
   * {@inheritdoc}
   */
  function getValue() {
    return $this->get('value')->value;
  }

  /**
   * {@inheritdoc}
   */
  function setValue($value) {
    return $this->set('value', $value);
  }

  /**
   * {@inheritdoc}
   */
  function getValueType() {
    return $this->get('value_type')->value;
  }

  /**
   * {@inheritdoc}
   */
  function setValueType($value_type) {
    return $this->set('value_type', $value_type);
  }

  /**
   * {@inheritdoc}
   */
  function getOwner() {
    return $this->get('user_id')->entity;
  }

  /**
   * {@inheritdoc}
   */
  function setOwner(UserInterface $account) {
    $this->set('user_id', $account->id());
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  function getOwnerId() {
    return $this->get('user_id')->target_id;
  }

  /**
   * {@inheritdoc}
   */
  function setOwnerId($uid) {
    $this->set('user_id', $uid);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  function getCreatedTime() {
    return $this->get('timestamp')->value;
  }

  /**
   * {@inheritdoc}
   */
  function setCreatedTime($timestamp) {
    return $this->set('timestamp', $timestamp);
  }

  /**
   * {@inheritdoc}
   */
  function getSource() {
    return $this->get('vote_source')->value;
  }

  /**
   * {@inheritdoc}
   */
  function setSource($source) {
    return $this->set('vote_source', $source);
  }

  /**
   * {@inheritdoc}
   */
  static function baseFieldDefinitions(EntityTypeInterface $entity_type) {

    $fields['id'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('ID'))
      ->setDescription(t('The vote ID.'))
      ->setReadOnly(TRUE)
      ->setSetting('unsigned', TRUE);

    $fields['uuid'] = BaseFieldDefinition::create('uuid')
      ->setLabel(t('UUID'))
      ->setDescription(t('The vote UUID.'))
      ->setReadOnly(TRUE);

    $fields['type'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Type'))
      ->setDescription(t('The vote type.'))
      ->setSetting('target_type', 'vote_type')
      ->setReadOnly(TRUE);

    $fields['entity_type'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Entity Type'))
      ->setDescription(t('The type from the voted entity.'))
      ->setDefaultValue('node')
      ->setSettings([
        'max_length' => 64,
      ])
      ->setRequired(TRUE);

    $fields['entity_id'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Voted entity'))
      ->setDescription(t('The ID from the voted entity'))
      ->setDefaultValue(0)
      ->setRequired(TRUE);

    $fields['value'] = BaseFieldDefinition::create('float')
      ->setLabel(t('Value'))
      ->setDescription(t('The numeric value of the vote.'))
      ->setDefaultValue(0)
      ->setRequired(TRUE);

    $fields['value_type'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Value Type'))
      ->setSettings([
        'max_length' => 64,
      ])
      ->setDefaultValue('percent');

    $fields['user_id'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Authored by'))
      ->setDescription(t('The user who submitted the vote.'))
      ->setSetting('target_type', 'user')
      ->setDefaultValueCallback('Drupal\votingapi\Entity\Vote::getCurrentUserId');

    $fields['timestamp'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created'))
      ->setDescription(t('The time that the entity was created.'))
      ->setDefaultValueCallback('time');

    $fields['vote_source'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Vote Source'))
      ->setDescription(t('The IP from the user who submitted the vote.'))
      ->setDefaultValueCallback('Drupal\votingapi\Entity\Vote::getCurrentIp')
      ->setSettings([
        'max_length' => 255,
      ]);

    return $fields;
  }

  /**
   * Default value callback for 'user' base field definition.
   *
   * @see ::baseFieldDefinitions()
   *
   * @return array
   *   An array of default values.
   */
  static function getCurrentUserId() {
    return \Drupal::currentUser()->id();
  }

  /**
   * Default value callback for 'user' base field definition.
   *
   * @see ::baseFieldDefinitions()
   *
   * @return array
   *   An array of default values.
   */
  static function getCurrentIp() {
    return \Drupal::request()->getClientIp();
  }

  /**
   * Update voting results when a new vote is cast.
   *
   * @param \Drupal\Core\Entity\EntityStorageInterface $storage
   * @param bool|TRUE $update
   */
  function postSave(EntityStorageInterface $storage, $update = TRUE) {
    if (\Drupal::config('votingapi.settings')
        ->get('calculation_schedule') == 'immediate'
    ) {
      \Drupal::service('plugin.manager.votingapi.resultfunction')
        ->recalculateResults(
          $this->getVotedEntityType(),
          $this->getVotedEntityId(),
          $this->bundle()
        );
      $cache_tag = $this->getVotedEntityType() . ':' . $this->getVotedEntityId();
      Cache::invalidateTags([$cache_tag]);
    }
  }

  /**
   * If a vote is deleted, the results needs to be updated.
   *
   * @param \Drupal\Core\Entity\EntityStorageInterface $storage
   * @param array $entities
   */
  static function postDelete(EntityStorageInterface $storage, array $entities) {
    parent::postDelete($storage, $entities);

    foreach ($entities as $entity) {
      \Drupal::service('plugin.manager.votingapi.resultfunction')
        ->recalculateResults(
          $entity->getVotedEntityType(),
          $entity->getVotedEntityId(),
          $entity->bundle()
        );
    }
  }

}
