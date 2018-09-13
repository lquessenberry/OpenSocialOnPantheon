<?php

namespace Drupal\profile\Entity;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\profile\Event\ProfileEvents;
use Drupal\profile\Event\ProfileLabelEvent;
use Drupal\user\UserInterface;

/**
 * Defines the profile entity class.
 *
 * @ContentEntityType(
 *   id = "profile",
 *   label = @Translation("Profile"),
 *   bundle_label = @Translation("Profile"),
 *   handlers = {
 *     "storage" = "Drupal\profile\ProfileStorage",
 *     "view_builder" = "Drupal\profile\ProfileViewBuilder",
 *     "views_data" = "Drupal\profile\ProfileViewsData",
 *     "access" = "Drupal\profile\ProfileAccessControlHandler",
 *     "permission_provider" = "Drupal\profile\ProfilePermissionProvider",
 *     "list_builder" = "Drupal\profile\ProfileListBuilder",
 *     "form" = {
 *       "default" = "Drupal\profile\Form\ProfileForm",
 *       "add" = "Drupal\profile\Form\ProfileForm",
 *       "edit" = "Drupal\profile\Form\ProfileForm",
 *       "delete" = "Drupal\profile\Form\ProfileDeleteForm",
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\DefaultHtmlRouteProvider",
 *     },
 *   },
 *   bundle_entity_type = "profile_type",
 *   field_ui_base_route = "entity.profile_type.edit_form",
 *   admin_permission = "administer profile",
 *   permission_granularity = "bundle",
 *   base_table = "profile",
 *   revision_table = "profile_revision",
 *   fieldable = TRUE,
 *   entity_keys = {
 *     "id" = "profile_id",
 *     "revision" = "revision_id",
 *     "bundle" = "type",
 *     "uuid" = "uuid"
 *   },
 *  links = {
 *    "canonical" = "/profile/{profile}",
 *    "edit-form" = "/profile/{profile}/edit",
 *    "delete-form" = "/profile/{profile}/delete",
 *    "collection" = "/admin/config/people/profiles",
 *    "set-default" = "/profile/{profile}/set-default"
 *   },
 *   common_reference_target = TRUE,
 * )
 */
class Profile extends ContentEntityBase implements ProfileInterface {

  use EntityChangedTrait;
  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function label() {
    $profile_type = ProfileType::load($this->bundle());
    $label = $this->t('@type profile #@id', [
      '@type' => $profile_type->label(),
      '@id' => $this->id(),
    ]);
    // Allow the label to be overridden.
    $event = new ProfileLabelEvent($this, $label);
    $event_dispatcher = \Drupal::service('event_dispatcher');
    $event_dispatcher->dispatch(ProfileEvents::PROFILE_LABEL, $event);
    $label = $event->getLabel();

    return $label;
  }

  /**
   * {@inheritdoc}
   */
  public function getOwnerId() {
    return $this->get('uid')->target_id;
  }

  /**
   * {@inheritdoc}
   */
  public function setOwnerId($uid) {
    $this->set('uid', $uid);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getOwner() {
    return $this->get('uid')->entity;
  }

  /**
   * {@inheritdoc}
   */
  public function setOwner(UserInterface $account) {
    $this->set('uid', $account->id());
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function isActive() {
    return (bool) $this->get('status')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setActive($active) {
    $this->set('status', (bool) $active);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function isDefault() {
    return (bool) $this->get('is_default')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setDefault($is_default) {
    $this->set('is_default', (bool) $is_default);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getCreatedTime() {
    return $this->get('created')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setCreatedTime($timestamp) {
    $this->set('created', $timestamp);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getRevisionCreationTime() {
    return $this->get('revision_timestamp')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setRevisionCreationTime($timestamp) {
    $this->set('revision_timestamp', $timestamp);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getRevisionAuthor() {
    return $this->get('revision_uid')->entity;
  }

  /**
   * {@inheritdoc}
   */
  public function setRevisionAuthorId($uid) {
    $this->set('revision_uid', $uid);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTagsToInvalidate() {
    $tags = parent::getCacheTagsToInvalidate();
    return Cache::mergeTags($tags, [
      'user:' . $this->getOwnerId(),
      'user_view',
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function preSave(EntityStorageInterface $storage) {
    /** @var \Drupal\profile\ProfileStorage $storage */
    parent::preSave($storage);

    // If this profile is active and the owner has no current default profile
    // of this type, set this as the default.
    if ($this->getOwner()) {
      if ($this->isActive() && !$this->isDefault()) {
        if (!$storage->loadDefaultByUser($this->getOwner(), $this->bundle())) {
          $this->setDefault(TRUE);
        }
      }
      // Only active profiles can be default.
      elseif (!$this->isActive()) {
        $this->setDefault(FALSE);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function postSave(EntityStorageInterface $storage, $update = TRUE) {
    /** @var \Drupal\profile\ProfileStorage $storage */
    parent::postSave($storage, $update);

    // Check if this profile is, or became the default.
    if ($this->getOwner()) {
      if ($this->isDefault()) {
        /** @var \Drupal\profile\Entity\ProfileInterface[] $profiles */
        $profiles = $storage->loadMultipleByUser($this->getOwner(), $this->bundle());

        // Ensure that all other profiles are set to not default.
        foreach ($profiles as $profile) {
          if ($profile->id() != $this->id() && $profile->isDefault()) {
            $profile->setDefault(FALSE);
            $profile->save();
          }
        }
      }
      // If this isn't the default, try to set a new one.
      elseif (!$storage->loadDefaultByUser($this->getOwner(), $this->bundle())) {
        /** @var \Drupal\profile\Entity\ProfileInterface $profile */
        if ($profile = $storage->loadByUser($this->getOwner(), $this->bundle())) {
          $profile->setDefault(TRUE);
          $profile->save();
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['uid'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Owner'))
      ->setDescription(t('The user that owns this profile.'))
      ->setRevisionable(TRUE)
      ->setSetting('target_type', 'user')
      ->setSetting('handler', 'default');

    $fields['status'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Active'))
      ->setDescription(t('Whether the profile is active.'))
      ->setDefaultValue(TRUE)
      ->setRevisionable(TRUE);

    $fields['is_default'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Default'))
      ->setDescription(t('Whether this is the default profile.'))
      ->setRevisionable(TRUE);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created'))
      ->setDescription(t('The time when the profile was created.'))
      ->setRevisionable(TRUE);

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setDescription(t('The time when the profile was last edited.'))
      ->setRevisionable(TRUE);

    return $fields;
  }

}
