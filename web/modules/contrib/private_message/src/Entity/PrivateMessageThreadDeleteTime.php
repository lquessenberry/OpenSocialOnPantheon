<?php

namespace Drupal\private_message\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\user\UserInterface;

/**
 * Defines the PrivateMessageThreadDeleteTime entity.
 *
 * @ContentEntityType(
 *   id = "pm_thread_delete_time",
 *   label = @Translation("Private Message Thread Delete Time"),
 *   handlers = {
 *     "views_data" = "Drupal\views\EntityViewsData",
 *     "access" = "Drupal\private_message\Entity\Access\PrivateMessageThreadDeleteTimeAccessControlHandler",
 *   },
 *   base_table = "pm_thread_delete_time",
 *   fieldable = FALSE,
 *   entity_keys = {
 *     "id" = "id",
 *     "uuid" = "uuid"
 *   },
 * )
 */
class PrivateMessageThreadDeleteTime extends ContentEntityBase implements PrivateMessageThreadDeleteTimeInterface {

  /**
   * {@inheritdoc}
   */
  public function setDeleteTime($timestamp) {
    $this->set('delete_time', $timestamp);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getDeleteTime() {
    return $this->get('delete_time')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function getOwner() {
    return $this->get('owner')->entity;
  }

  /**
   * {@inheritdoc}
   */
  public function getOwnerId() {
    return $this->get('owner')->target_id;
  }

  /**
   * {@inheritdoc}
   */
  public function setOwnerId($uid) {
    $this->set('owner', $uid);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setOwner(UserInterface $account) {
    $this->set('owner', $account->id());
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);
    $fields['id']->setLabel(t('Custom private message ID'))
      ->setDescription(t('The private message ID.'));

    $fields['uuid']->setDescription(t('The custom private message UUID.'));

    // Owner of the private message.
    // Entity reference field, holds the reference to the user object. The view
    // shows the user name field of the user. No form field is provided, as the
    // user will always be the current user.
    $fields['owner'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('User Name'))
      ->setDescription(t('The Name of the associated user.'))
      ->setSetting('target_type', 'user')
      ->setSetting('handler', 'default');

    $fields['delete_time'] = BaseFieldDefinition::create('timestamp')
      ->setLabel(t('Delete Time'))
      ->setDescription(t('The time at which the user deleted the private message thread that references this entity'));

    return $fields;
  }

}
