<?php

namespace Drupal\group\Entity;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\user\EntityOwnerTrait;
use Drupal\user\UserInterface;

/**
 * Defines the Group content entity.
 *
 * @ingroup group
 *
 * @ContentEntityType(
 *   id = "group_content",
 *   label = @Translation("Group content"),
 *   label_singular = @Translation("group content item"),
 *   label_plural = @Translation("group content items"),
 *   label_count = @PluralTranslation(
 *     singular = "@count group content item",
 *     plural = "@count group content items"
 *   ),
 *   bundle_label = @Translation("Group content type"),
 *   handlers = {
 *     "storage" = "Drupal\group\Entity\Storage\GroupContentStorage",
 *     "storage_schema" = "Drupal\group\Entity\Storage\GroupContentStorageSchema",
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "views_data" = "Drupal\group\Entity\Views\GroupContentViewsData",
 *     "list_builder" = "Drupal\group\Entity\Controller\GroupContentListBuilder",
 *     "route_provider" = {
 *       "html" = "Drupal\group\Entity\Routing\GroupContentRouteProvider",
 *     },
 *     "form" = {
 *       "add" = "Drupal\group\Entity\Form\GroupContentForm",
 *       "edit" = "Drupal\group\Entity\Form\GroupContentForm",
 *       "delete" = "Drupal\group\Entity\Form\GroupContentDeleteForm",
 *       "group-join" = "Drupal\group\Form\GroupJoinForm",
 *       "group-leave" = "Drupal\group\Form\GroupLeaveForm",
 *     },
 *     "access" = "Drupal\group\Entity\Access\GroupContentAccessControlHandler",
 *   },
 *   base_table = "group_content",
 *   data_table = "group_content_field_data",
 *   translatable = TRUE,
 *   entity_keys = {
 *     "id" = "id",
 *     "uuid" = "uuid",
 *     "owner" = "uid",
 *     "langcode" = "langcode",
 *     "bundle" = "type",
 *     "label" = "label"
 *   },
 *   links = {
 *     "add-form" = "/group/{group}/content/add/{plugin_id}",
 *     "add-page" = "/group/{group}/content/add",
 *     "canonical" = "/group/{group}/content/{group_content}",
 *     "collection" = "/group/{group}/content",
 *     "create-form" = "/group/{group}/content/create/{plugin_id}",
 *     "create-page" = "/group/{group}/content/create",
 *     "delete-form" = "/group/{group}/content/{group_content}/delete",
 *     "edit-form" = "/group/{group}/content/{group_content}/edit"
 *   },
 *   bundle_entity_type = "group_content_type",
 *   field_ui_base_route = "entity.group_content_type.edit_form",
 *   permission_granularity = "bundle",
 *   constraints = {
 *     "GroupContentCardinality" = {}
 *   }
 * )
 */
class GroupContent extends ContentEntityBase implements GroupContentInterface {

  use EntityChangedTrait;
  use EntityOwnerTrait;

  /**
   * {@inheritdoc}
   */
  public function getGroupContentType() {
    return $this->type->entity;
  }

  /**
   * {@inheritdoc}
   */
  public function getGroup() {
    return $this->gid->entity;
  }

  /**
   * {@inheritdoc}
   */
  public function getEntity() {
    return $this->entity_id->entity;
  }

  /**
   * {@inheritdoc}
   */
  public function getContentPlugin() {
    return $this->getGroupContentType()->getContentPlugin();
  }

  /**
   * {@inheritdoc}
   */
  public static function loadByContentPluginId($plugin_id) {
    /** @var \Drupal\group\Entity\Storage\GroupContentStorageInterface $storage */
    $storage = \Drupal::entityTypeManager()->getStorage('group_content');
    return $storage->loadByContentPluginId($plugin_id);
  }

  /**
   * {@inheritdoc}
   */
  public static function loadByEntity(ContentEntityInterface $entity) {
    /** @var \Drupal\group\Entity\Storage\GroupContentStorageInterface $storage */
    $storage = \Drupal::entityTypeManager()->getStorage('group_content');
    return $storage->loadByEntity($entity);
  }

  /**
   * {@inheritdoc}
   */
  public function label() {
    return $this->getContentPlugin()->getContentLabel($this);
  }

  /**
   * {@inheritdoc}
   */
  protected function urlRouteParameters($rel) {
    $uri_route_parameters = parent::urlRouteParameters($rel);
    $uri_route_parameters['group'] = $this->getGroup()->id();
    // These routes depend on the plugin ID.
    if (in_array($rel, ['add-form', 'create-form'])) {
      $uri_route_parameters['plugin_id'] = $this->getContentPlugin()->getPluginId();
    }
    return $uri_route_parameters;
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
  public function getChangedTime() {
    return $this->get('changed')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function preSave(EntityStorageInterface $storage) {
    parent::preSave($storage);

    // Set the label so the DB also reflects it.
    $this->set('label', $this->label());
  }

  /**
   * {@inheritdoc}
   */
  public function postSave(EntityStorageInterface $storage, $update = TRUE) {
    parent::postSave($storage, $update);

    // For memberships, we generally need to rebuild the group role cache for
    // the member's user account in the target group.
    $rebuild_group_role_cache = $this->getContentPlugin()->getPluginId() == 'group_membership';

    if ($update === FALSE) {
      // We want to make sure that the entity we just added to the group behaves
      // as a grouped entity. This means we may need to update access records,
      // flush some caches containing the entity or perform other operations we
      // cannot possibly know about. Lucky for us, all of that behavior usually
      // happens when saving an entity so let's re-save the added entity.
      $this->getEntity()->save();
    }

    // If a membership gets updated, but the member's roles haven't changed, we
    // do not need to rebuild the group role cache for the member's account.
    elseif ($rebuild_group_role_cache) {
      $new = array_column($this->group_roles->getValue(), 'target_id');
      $old = array_column($this->original->group_roles->getValue(), 'target_id');
      sort($new);
      sort($old);
      $rebuild_group_role_cache = ($new != $old);
    }

    if ($rebuild_group_role_cache) {
      /** @var \Drupal\group\Entity\Storage\GroupRoleStorageInterface $role_storage */
      $role_storage = \Drupal::entityTypeManager()->getStorage('group_role');
      $role_storage->resetUserGroupRoleCache($this->getEntity(), $this->getGroup());
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function postDelete(EntityStorageInterface $storage, array $entities) {
    parent::postDelete($storage, $entities);

    /** @var GroupContentInterface[] $entities */
    foreach ($entities as $group_content) {
      if ($entity = $group_content->getEntity()) {
        // For the same reasons we re-save entities that are added to a group,
        // we need to re-save entities that were removed from one. See
        // ::postSave(). We only save the entity if it still exists to avoid
        // trying to save an entity that just got deleted and triggered the
        // deletion of its group content entities.
        // @todo Revisit when https://www.drupal.org/node/2754399 lands.
        if ($entity = \Drupal::entityTypeManager()->getStorage($entity->getEntityTypeId())->loadUnchanged($entity->id())) {
          // The entity is reloaded from storage, so it has not been deleted.
          $entity->save();
        }

        // If a membership gets deleted, we need to reset the internal group
        // roles cache for the member in that group, but only if the user still
        // exists. Otherwise, it doesn't matter as the user ID will become void.
        if ($group_content->getContentPlugin()->getPluginId() == 'group_membership') {
          /** @var \Drupal\group\Entity\Storage\GroupRoleStorageInterface $role_storage */
          $role_storage = \Drupal::entityTypeManager()->getStorage('group_role');
          $role_storage->resetUserGroupRoleCache($group_content->getEntity(), $group_content->getGroup());
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getListCacheTagsToInvalidate() {
    $tags = parent::getListCacheTagsToInvalidate();

    $group_id = $this->get('gid')->target_id;
    $entity_id = $this->get('entity_id')->target_id;
    $plugin_id = $this->getGroupContentType()->getContentPluginId();

    // A specific group gets any content, regardless of plugin used.
    // E.g.: A group's list of entities can be flushed with this.
    $tags[] = "group_content_list:group:$group_id";

    // A specific entity gets added to any group, regardless of plugin used.
    // E.g.: An entity's list of groups can be flushed with this.
    $tags[] = "group_content_list:entity:$entity_id";

    // Any entity gets added to any group using a specific plugin.
    // E.g.: A list of all memberships anywhere can be flushed with this.
    $tags[] = "group_content_list:plugin:$plugin_id";

    // A specific group gets any content using a specific plugin.
    // E.g.: A group's list of members can be flushed with this.
    $tags[] = "group_content_list:plugin:$plugin_id:group:$group_id";

    // A specific entity gets added to any group using a specific plugin.
    // E.g.: A user's list of memberships can be flushed with this.
    $tags[] = "group_content_list:plugin:$plugin_id:entity:$entity_id";

    return $tags;
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);
    $fields += static::ownerBaseFieldDefinitions($entity_type);

    $fields['gid'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Parent group'))
      ->setDescription(t('The group containing the entity.'))
      ->setSetting('target_type', 'group')
      ->setReadOnly(TRUE)
      ->setRequired(TRUE);

    // Borrowed this logic from the Comment module.
    // Warning! May change in the future: https://www.drupal.org/node/2346347
    $fields['entity_id'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Content'))
      ->setDescription(t('The entity to add to the group.'))
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete',
        'weight' => 5,
        'settings' => [
          'match_operator' => 'CONTAINS',
          'size' => '60',
          'placeholder' => '',
        ],
      ])
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE)
      ->setRequired(TRUE);

    $fields['label'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Title'))
      ->setReadOnly(TRUE)
      ->setTranslatable(TRUE)
      ->setSetting('max_length', 255)
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'string',
        'weight' => -5,
      ]);

    $fields['uid']
      ->setLabel(t('Group content creator'))
      ->setDescription(t('The username of the group content creator.'))
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created on'))
      ->setDescription(t('The time that the group content was created.'))
      ->setTranslatable(TRUE);

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed on'))
      ->setDescription(t('The time that the group content was last edited.'))
      ->setTranslatable(TRUE);

    if (\Drupal::moduleHandler()->moduleExists('path')) {
      $fields['path'] = BaseFieldDefinition::create('path')
        ->setLabel(t('URL alias'))
        ->setTranslatable(TRUE)
        ->setDisplayOptions('form', [
          'type' => 'path',
          'weight' => 30,
        ])
        ->setDisplayConfigurable('form', TRUE)
        ->setComputed(TRUE);
    }

    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public static function bundleFieldDefinitions(EntityTypeInterface $entity_type, $bundle, array $base_field_definitions) {
    // Borrowed this logic from the Comment module.
    // Warning! May change in the future: https://www.drupal.org/node/2346347
    if ($group_content_type = GroupContentType::load($bundle)) {
      $plugin = $group_content_type->getContentPlugin();

      /** @var \Drupal\Core\Field\BaseFieldDefinition $original */
      $original = $base_field_definitions['entity_id'];

      // Recreated the original entity_id field so that it does not contain any
      // data in its "propertyDefinitions" or "schema" properties because those
      // were set based on the base field which had no clue what bundle to serve
      // up until now. This is a bug in core because we can't simply unset those
      // two properties, see: https://www.drupal.org/node/2346329
      $fields['entity_id'] = BaseFieldDefinition::create('entity_reference')
        ->setLabel($plugin->getEntityReferenceLabel() ?: $original->getLabel())
        ->setDescription($plugin->getEntityReferenceDescription() ?: $original->getDescription())
        ->setConstraints($original->getConstraints())
        ->setDisplayOptions('view', $original->getDisplayOptions('view'))
        ->setDisplayOptions('form', $original->getDisplayOptions('form'))
        ->setDisplayConfigurable('view', $original->isDisplayConfigurable('view'))
        ->setDisplayConfigurable('form', $original->isDisplayConfigurable('form'))
        ->setRequired($original->isRequired());

      foreach ($plugin->getEntityReferenceSettings() as $name => $setting) {
        $fields['entity_id']->setSetting($name, $setting);
      }

      return $fields;
    }

    return [];
  }

}
