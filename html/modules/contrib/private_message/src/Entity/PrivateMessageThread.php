<?php

namespace Drupal\private_message\Entity;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Defines the Private Message Thread entity.
 *
 * @ContentEntityType(
 *   id = "private_message_thread",
 *   label = @Translation("Private Message Thread"),
 *   handlers = {
 *     "view_builder" = "Drupal\private_message\Entity\Builder\PrivateMessageThreadViewBuilder",
 *     "views_data" = "Drupal\views\EntityViewsData",
 *     "access" = "Drupal\private_message\Entity\Access\PrivateMessageThreadAccessControlHandler",
 *     "form" = {
 *       "delete" = "Drupal\private_message\Form\PrivateMessageThreadDeleteForm",
 *     },
 *   },
 *   base_table = "private_message_threads",
 *   admin_permission = "administer private messages",
 *   fieldable = TRUE,
 *   entity_keys = {
 *     "id" = "id",
 *     "uuid" = "uuid"
 *   },
 *   links = {
 *     "canonical" = "/private_messages/{private_message_thread}",
 *     "delete-form" = "/private_messagethread/{private_message_thread}/delete",
 *   },
 *   field_ui_base_route = "private_message.private_message_thread_settings",
 * )
 */
class PrivateMessageThread extends ContentEntityBase implements PrivateMessageThreadInterface {

  /**
   * {@inheritdoc}
   */
  public function addMember(AccountInterface $account) {
    $this->get('members')->appendItem($account->id());
    $this->addLastAccessTime($account);
    $this->addLastDeleteTime($account);

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function addMemberById($id) {
    $this->get('members')->appendItem($id);

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getMembers() {
    return $this->get('members')->referencedEntities();
  }

  /**
   * {@inheritdoc}
   */
  public function isMember($id) {
    $members = $this->getMembers();
    foreach ($members as $member) {
      if ($member->id() == $id) {
        return TRUE;
      }
    }

    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function addMessage(PrivateMessageInterface $privateMessage) {
    $this->get('private_messages')->appendItem($privateMessage->id());

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function addMessageById($id) {
    $this->get('private_messages')->appendItem($id);

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getMessages() {
    return $this->get('private_messages')->referencedEntities();
  }

  /**
   * {@inheritdoc}
   */
  public function filterNewestMessages() {
    $messages = $this->getMessages();
    if (count($messages) > \Drupal::config('private_message_thread.settings')->get('message_count')) {
      $list = $this->get('private_messages');
      $filtered_messages = array_slice($messages, -1 * \Drupal::config('private_message_thread.settings')->get('message_count'));
      $first_message = array_shift($filtered_messages);
      $first_key = $first_message->id();
      foreach ($list->referencedEntities() as $list_item) {
        if ($list_item->id() < $first_key) {
          $list->removeItem(0);
        }
        else {
          break;
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getNewestMessageCreationTimestamp() {
    $messages = $this->getMessages();
    $last_timestamp = 0;
    foreach ($messages as $message) {
      $creation_date = $message->get('created')->value;
      $last_timestamp = $creation_date > $last_timestamp ? $creation_date : $last_timestamp;
    }

    return $last_timestamp;
  }

  /**
   * {@inheritdoc}
   */
  public function addLastAccessTime(AccountInterface $account) {
    $last_access_time = PrivateMessageThreadAccessTime::create([
      'owner' => $account->id(),
      'access_time' => \Drupal::time()->getRequestTime(),
    ]);

    $this->get('last_access_time')->appendItem($last_access_time);
  }

  /**
   * {@inheritdoc}
   */
  public function getLastAccessTime(AccountInterface $account) {
    $private_message_last_access = FALSE;
    foreach ($this->get('last_access_time') as $last_access_time) {
      if ($last_access_time->entity->getOwnerId() == $account->id()) {
        $private_message_last_access = $last_access_time;

        break;
      }
    }

    return $private_message_last_access;
  }

  /**
   * {@inheritdoc}
   */
  public function getLastAccessTimes() {
    return $this->get('last_access_time')->referencedEntities();
  }

  /**
   * {@inheritdoc}
   */
  public function getLastAccessTimestamp(AccountInterface $account) {
    $last_access = $this->getLastAccessTime($account);

    return $last_access ? $last_access->entity->get('access_time')->value : FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function updateLastAccessTime(AccountInterface $account) {
    $private_message_access_time = $this->getLastAccessTime($account);

    if ($private_message_access_time) {
      $private_message_access_time->entity->setAccessTime(\Drupal::time()->getRequestTime())->save();
    }
    else {
      $this->addLastAccessTime($account);
    }

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function addLastDeleteTime(AccountInterface $account) {
    $last_delete_time = PrivateMessageThreadDeleteTime::create([
      'owner' => $account->id(),
      'delete_time' => 0,
    ]);

    $this->get('last_delete_time')->appendItem($last_delete_time);
  }

  /**
   * {@inheritdoc}
   */
  public function getLastDeleteTimestamp(AccountInterface $account) {
    $private_message_delete_time = FALSE;
    foreach ($this->get('last_delete_time') as $last_delete_time) {
      if ($last_delete_time->entity->getOwnerId() == $account->id()) {
        $private_message_delete_time = $last_delete_time->entity->get('delete_time')->value;

        break;
      }
    }

    return $private_message_delete_time;
  }

  /**
   * {@inheritdoc}
   */
  public function getLastDeleteTime(AccountInterface $account) {
    foreach ($this->get('last_delete_time') as $last_delete_time) {
      if ($last_delete_time->entity->getOwnerId() == $account->id()) {
        return $last_delete_time->entity;
      }
    }

    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function getLastDeleteTimes() {
    return $this->get('last_delete_time')->referencedEntities();
  }

  /**
   * {@inheritdoc}
   */
  public function updateLastDeleteTime(AccountInterface $account) {
    $private_message_delete_time = $this->getLastDeleteTime($account);

    if ($private_message_delete_time) {
      $private_message_delete_time->setDeleteTime(\Drupal::time()->getRequestTime());
      $private_message_delete_time->save();
    }
    else {
      $this->addLastDeleteTime($account);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function save() {
    parent::save();

    $this->clearCacheTags();
  }

  /**
   * {@inheritdoc}
   */
  public function delete(AccountInterface $account = NULL) {
    if ($account) {
      $this->updateLastDeleteTime($account);
      $last_creation_timestamp = $this->getNewestMessageCreationTimestamp();
      $delete = TRUE;
      $last_delete_times = $this->getLastDeleteTimes();
      foreach ($last_delete_times as $last_delete_time) {
        if ($last_delete_time->getDeleteTime() < $last_creation_timestamp) {
          $delete = FALSE;

          break;
        }
      }

      if ($delete) {
        $this->deleteReferencedEntities();
        parent::delete();
      }
    }
    else {
      $this->deleteReferencedEntities();
      parent::delete();
    }

    $this->clearCacheTags();
  }

  /**
   * {@inheritdoc}
   */
  public function filterUserDeletedMessages(AccountInterface $account) {
    $last_delete_timestamp = $this->getLastDeleteTimestamp($account);
    $messages = $this->getMessages();
    $start_index = FALSE;
    foreach ($messages as $index => $message) {
      if ($message->getCreatedTime() > $last_delete_timestamp) {
        $start_index = $index;

        break;
      }
    }

    if ($start_index !== FALSE) {
      return array_slice($messages, $start_index);
    }

    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getUpdatedTime() {
    return $this->get('updated')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function clearCacheTags() {
    $tags = [];

    foreach ($this->getMembers() as $member) {
      $tags[] = 'private_message_inbox_block:uid:' . $member->id();
      $tags[] = 'private_message_notification_block:uid:' . $member->id();
    }

    Cache::invalidateTags($tags);
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entityType) {
    $fields = parent::baseFieldDefinitions($entityType);

    $fields['id']->setLabel(t('Private message thread ID'))
      ->setDescription(t('The private message thread ID.'));

    $fields['uuid']->setDescription(t('The custom private message thread UUID.'));

    $fields['updated'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Updated'))
      ->setDescription(t('The most recent time at which the thread was updated'));

    // Member(s) of the private message thread.
    // Entity reference field, holds the reference to user objects.
    $fields['members'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Member(s)'))
      ->setDescription(t('The member(s) of the private message thread'))
      ->setSetting('target_type', 'user')
      ->setSetting('handler', 'default')
      ->setCardinality(FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED)
      ->addConstraint('private_message_thread_member')
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'entity_reference_label',
        'weight' => -3,
      ])
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete',
        'settings' => [
          'match_operator' => 'CONTAINS',
          'size' => 60,
          'autocomplete_type' => 'tags',
          'placeholder' => '',
        ],
        'weight' => -3,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    // Private messages in this thread.
    // Entity reference field, holds the reference to user objects.
    $fields['private_messages'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Messages'))
      ->setDescription(t('The private messages that belong to this thread'))
      ->setSetting('target_type', 'private_message')
      ->setSetting('handler', 'default')
      ->setCardinality(FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED)
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'entity_reference_entity_view',
      ])
      ->setDisplayConfigurable('view', TRUE);

    // Last access
    // Timestamps for each member, representing the last time at which they
    // accessed the private message thread.
    $fields['last_access_time'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Last Access Times'))
      ->setDescription(t('Timestamps at which members of this private message thread last accessed the thread'))
      ->setSetting('target_type', 'pm_thread_access_time')
      ->setSetting('handler', 'default')
      ->setCardinality(FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED);

    // Last delete
    // Timestamps for each member, representing the last time at which they
    // deleted the private message thread. Note that the thread is only deleted
    // from the database if/when all members have deleted the thread. Until that
    // point, this value is used to determine the first message to be shown to a
    // user if they have deleted the thread, then re-entered a conversation with
    // the other members of the thread.
    $fields['last_delete_time'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Last Delete Times'))
      ->setDescription(t('Timestamps at which members of this private message thread last deleted the thread'))
      ->setSetting('target_type', 'pm_thread_delete_time')
      ->setSetting('handler', 'default')
      ->setCardinality(FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED);

    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags() {
    $tags = parent::getCacheTags();
    $tags[] = 'private_message_thread:' . $this->id() . ':view:uid:' . \Drupal::currentUser()->id();

    return $tags;
  }

  /**
   * Delete the thread from the database, as well as all reference entities.
   */
  private function deleteReferencedEntities() {
    $messages = $this->getMessages();
    foreach ($messages as $message) {
      $message->delete();
    }

    $last_access_times = $this->getLastAccessTimes();
    foreach ($last_access_times as $last_access_time) {
      $last_access_time->delete();
    }

    $last_delete_times = $this->getLastDeleteTimes();
    foreach ($last_delete_times as $last_delete_time) {
      $last_delete_time->delete();
    }
  }

}
