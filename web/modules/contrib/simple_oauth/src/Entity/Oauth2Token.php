<?php

namespace Drupal\simple_oauth\Entity;

use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\simple_oauth\Oauth2ScopeInterface;
use Drupal\user\Entity\Role;
use Drupal\user\EntityOwnerTrait;

/**
 * Defines the Oauth2 Token entity.
 *
 * @ingroup simple_oauth
 *
 * @ContentEntityType(
 *   id = "oauth2_token",
 *   label = @Translation("OAuth2 token"),
 *   bundle_label = @Translation("Token type"),
 *   handlers = {
 *     "storage_schema" = "Drupal\simple_oauth\Entity\Oauth2TokenStorageSchema",
 *     "list_builder" = "Drupal\simple_oauth\Entity\Oauth2TokenListBuilder",
 *     "form" = {
 *       "delete" = "Drupal\simple_oauth\Entity\Form\Oauth2TokenDeleteForm",
 *     },
 *     "access" = "Drupal\simple_oauth\Entity\Access\AccessTokenAccessControlHandler",
 *   },
 *   base_table = "oauth2_token",
 *   admin_permission = "administer simple_oauth entities",
 *   field_indexes = {
 *     "value"
 *   },
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "value",
 *     "bundle" = "bundle",
 *     "uuid" = "uuid"
 *   },
 *   bundle_entity_type = "oauth2_token_type",
 *   links = {
 *     "canonical" = "/admin/content/simple_oauth/oauth2_token/{oauth2_token}",
 *     "delete-form" = "/admin/content/simple_oauth/oauth2_token/{oauth2_token}/delete"
 *   },
 *   list_cache_tags = { "oauth2_token" },
 * )
 */
class Oauth2Token extends ContentEntityBase implements Oauth2TokenInterface {

  use EntityChangedTrait, EntityOwnerTrait;

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['id'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('ID'))
      ->setDescription(t('The ID of the Access Token entity.'))
      ->setReadOnly(TRUE);

    $fields['uuid'] = BaseFieldDefinition::create('uuid')
      ->setLabel(t('UUID'))
      ->setDescription(t('The UUID of the Access Token entity.'))
      ->setReadOnly(TRUE);

    $fields['bundle'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Bundle'))
      ->setDescription(t('The bundle property.'))
      ->setRevisionable(FALSE)
      ->setReadOnly(TRUE)
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'entity_reference_label',
        'weight' => 0,
      ])
      ->setSetting('target_type', 'oauth2_token_type');

    $fields['auth_user_id'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('User'))
      ->setDescription(t('The user ID of the user this access token is authenticating.'))
      ->setRevisionable(TRUE)
      ->setSetting('target_type', 'user')
      ->setSetting('handler', 'default')
      ->setDefaultValueCallback(static::class . '::getDefaultEntityOwner')
      ->setTranslatable(FALSE)
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'author',
        'weight' => 1,
      ])
      ->setCardinality(1)
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete',
        'weight' => 0,
        'settings' => [
          'match_operator' => 'CONTAINS',
          'size' => '60',
          'autocomplete_type' => 'tags',
          'placeholder' => '',
        ],
      ]);

    $fields['client'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Client'))
      ->setDescription(t('The consumer client for this Access Token.'))
      ->setRevisionable(TRUE)
      ->setSetting('target_type', 'consumer')
      ->setSetting('handler', 'default')
      ->setTranslatable(FALSE)
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'entity_reference_label',
        'weight' => 2,
      ])
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete',
        'weight' => 2,
      ]);

    $fields['scopes'] = BaseFieldDefinition::create('oauth2_scope_reference')
      ->setLabel(t('Scopes'))
      ->setDescription(t('The scopes for this Access Token.'))
      ->setRevisionable(TRUE)
      ->setCardinality(FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED)
      ->setTranslatable(FALSE)
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'oauth2_scope_reference_label',
        'weight' => 3,
      ])
      ->setDisplayOptions('form', [
        'type' => 'oauth2_scope_reference',
        'weight' => 3,
      ]);

    $fields['value'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Token'))
      ->setDescription(t('The token value.'))
      ->setSettings([
        'max_length' => 128,
        'text_processing' => 0,
      ])
      ->setRequired(TRUE)
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'timestamp',
        'weight' => 4,
      ]);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created'))
      ->setDescription(t('The time that the entity was created.'))
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'timestamp',
        'weight' => 5,
      ]);

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setDescription(t('The time that the entity was last edited.'))
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'timestamp',
        'weight' => 6,
      ]);

    $fields['expire'] = BaseFieldDefinition::create('timestamp')
      ->setLabel(t('Expire'))
      ->setDescription(t('The time when the token expires.'))
      ->setDisplayOptions('form', [
        'type' => 'datetime_timestamp',
        'weight' => 7,
      ])
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'timestamp',
        'weight' => 7,
      ])
      ->setRequired(TRUE);

    $fields['status'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Publishing status'))
      ->setDescription(t('A boolean indicating whether the token is available.'))
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'boolean',
        'weight' => 8,
      ])
      ->setRevisionable(TRUE)
      ->setTranslatable(TRUE)
      ->setDefaultValue(TRUE);

    return $fields;
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
  public function revoke() {
    $this->set('status', FALSE);
  }

  /**
   * {@inheritdoc}
   */
  public function isRevoked(): bool {
    return !$this->get('status')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function hasPermission(string $permission): bool {
    /** @var \Drupal\simple_oauth\Oauth2ScopeProviderInterface $scope_provider */
    $scope_provider = \Drupal::service('simple_oauth.oauth2_scope.provider');
    /** @var \Drupal\simple_oauth\Plugin\Field\FieldType\Oauth2ScopeReferenceItemListInterface $field */
    $field = $this->get('scopes');

    foreach ($field->getScopes() as $scope) {
      if (in_array($permission, $scope_provider->getFlattenPermissionTree($scope))) {
        return TRUE;
      }
    }

    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTagsToInvalidate() {
    // It's feasible there are millions of OAuth2 tokens in rotation; they're
    // used only for authentication, not for computing output. Hence it does not
    // make sense for an OAuth2 token to be a cacheable dependency. Consequently
    // generating a unique cache tag for every OAuth2 token entity should be
    // avoided. Therefore a single cache tag is used for all OAuth2 token
    // entities, including for lists.
    return ['oauth2_token'];
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags() {
    // Same reasoning as in ::getCacheTagsToInvalidate().
    return static::getCacheTagsToInvalidate();
  }

}
