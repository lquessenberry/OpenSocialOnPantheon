<?php

namespace Drupal\data_policy\Entity;

use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\user\UserInterface;

/**
 * Defines the User consent entity.
 *
 * @ingroup data_policy
 *
 * @ContentEntityType(
 *   id = "user_consent",
 *   label = @Translation("User consent"),
 *   label_collection = @Translation("User consents"),
 *   handlers = {
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "views_data" = "Drupal\data_policy\Entity\UserConsentViewsData",
 *     "list_builder" = "Drupal\data_policy\UserConsentListBuilder",
 *     "route_provider" = {
 *       "html" = "Drupal\data_policy\UserConsentHtmlRouteProvider",
 *     },
 *   },
 *   base_table = "user_consent",
 *   admin_permission = "overview user consents",
 *   entity_keys = {
 *     "id" = "id",
 *     "uuid" = "uuid",
 *     "uid" = "user_id",
 *     "langcode" = "langcode",
 *     "status" = "status",
 *   },
 *   links = {
 *     "collection" = "/admin/reports/user-consents",
 *   }
 * )
 */
class UserConsent extends ContentEntityBase implements UserConsentInterface {

  use EntityChangedTrait;

  /**
   * {@inheritdoc}
   */
  public function getName() {
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function setName($name) {
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
  public function getOwner() {
    return $this->get('user_id')->entity;
  }

  /**
   * {@inheritdoc}
   */
  public function getOwnerId() {
    return $this->get('user_id')->target_id;
  }

  /**
   * {@inheritdoc}
   */
  public function setOwnerId($uid) {
    $this->set('user_id', $uid);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setOwner(UserInterface $account) {
    $this->set('user_id', $account->id());
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function isPublished() {
    return (bool) $this->getEntityKey('status');
  }

  /**
   * {@inheritdoc}
   */
  public function setPublished($published) {
    $this->set('status', $published);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setRevision(DataPolicyInterface $data_policy) {
    $this->set('data_policy_revision_id', $data_policy->getRevisionId());

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['user_id'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Authored by'))
      ->setDescription(t('The user ID of author of the User consent entity.'))
      ->setRevisionable(TRUE)
      ->setSetting('target_type', 'user')
      ->setSetting('handler', 'default')
      ->setTranslatable(TRUE)
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'author',
        'weight' => 0,
      ])
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete',
        'weight' => 5,
        'settings' => [
          'match_operator' => 'CONTAINS',
          'size' => '60',
          'autocomplete_type' => 'tags',
          'placeholder' => '',
        ],
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['data_policy_revision_id'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Data policy revision ID'))
      ->setReadOnly(TRUE)
      ->setCardinality(FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED)
      ->setSetting('unsigned', TRUE);

    $fields['state'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('State of consent'))
      ->setReadOnly(TRUE)
      ->setSetting('unsigned', TRUE);

    $fields['required'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Required'));

    $fields['status'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Publishing status'))
      ->setDescription(t('A boolean indicating whether the User consent is published.'))
      ->setRevisionable(TRUE)
      ->setDefaultValue(TRUE);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created'))
      ->setDescription(t('The time that the entity was created.'));

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setDescription(t('The time that the entity was last edited.'));

    return $fields;
  }

  /**
   * Override of the default label() function to return a human-friendly name.
   *
   * @return \Drupal\Component\Render\MarkupInterface|string
   *   Return user display name.
   */
  public function label() {
    $user = $this->getOwner();
    if ($user && $user->getDisplayName()) {
      return $user->getDisplayName();
    }

    return '';
  }

}
