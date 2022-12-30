<?php

/**
 * @file
 * Post update functions for Group.
 */

use Drupal\Core\Config\Entity\ConfigEntityUpdater;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\group\Entity\GroupType;
use Drupal\group\Entity\GroupContentType;
use Drupal\group\Entity\Storage\GroupStorage;
use Drupal\user\Entity\Role;

/**
 * Recalculate group type and group content type dependencies after moving the
 * plugin configuration from the former to the latter in group_update_8006().
 */
function group_post_update_group_type_group_content_type_dependencies() {
  foreach (GroupType::loadMultiple() as $group_type) {
    $group_type->save();
  }

  foreach (GroupContentType::loadMultiple() as $group_type) {
    $group_type->save();
  }
}

/**
 * Recalculate group content type dependencies after updating the group content
 * enabler base plugin dependency logic.
 */
function group_post_update_group_content_type_dependencies() {
  foreach (GroupContentType::loadMultiple() as $group_type) {
    $group_type->save();
  }
}

/**
 * Grant the new 'access group overview' permission.
 */
function group_post_update_grant_access_overview_permission() {
  /** @var \Drupal\user\RoleInterface $role */
  foreach (Role::loadMultiple() as $role) {
    if ($role->hasPermission('administer group')) {
      $role->grantPermission('access group overview');
      $role->save();
    }
  }
}

/**
 * Fix cache contexts in views.
 */
function group_post_update_view_cache_contexts(&$sandbox) {
  if (!\Drupal::moduleHandler()->moduleExists('views')) {
    return;
  }
  // This will trigger the catch-all fix in group_view_presave().
  \Drupal::classResolver(ConfigEntityUpdater::class)->update($sandbox, 'view', function ($view) {
    return TRUE;
  });
}

/**
 * Update groups to be revisionable.
 */
function group_post_update_make_group_revisionable(&$sandbox) {
  $definition_update_manager = \Drupal::entityDefinitionUpdateManager();
  /** @var \Drupal\Core\Entity\EntityLastInstalledSchemaRepositoryInterface $last_installed_schema_repository */
  $last_installed_schema_repository = \Drupal::service('entity.last_installed_schema.repository');

  $entity_type = $definition_update_manager->getEntityType('group');
  $field_storage_definitions = $last_installed_schema_repository->getLastInstalledFieldStorageDefinitions('group');

  // Update the entity type definition.
  $entity_keys = $entity_type->getKeys();
  $entity_keys['revision'] = 'revision_id';
  $entity_keys['revision_translation_affected'] = 'revision_translation_affected';
  $entity_type->set('entity_keys', $entity_keys);
  $entity_type->set('revision_table', 'groups_revision');
  $entity_type->set('revision_data_table', 'groups_field_revision');
  $revision_metadata_keys = [
    'revision_default' => 'revision_default',
    'revision_user' => 'revision_user',
    'revision_created' => 'revision_created',
    'revision_log_message' => 'revision_log_message',
  ];
  $entity_type->set('revision_metadata_keys', $revision_metadata_keys);

  // Some revision data does not get set by core properly, this fixes it.
  $entity_type->setHandlerClass('storage', GroupStorage::class);

  // Update the field storage definitions and add the new ones required by a
  // revisionable entity type.
  $field_storage_definitions['label']->setRevisionable(TRUE);
  $field_storage_definitions['uid']->setRevisionable(TRUE);
  $field_storage_definitions['langcode']->setRevisionable(TRUE);
  $field_storage_definitions['created']->setRevisionable(TRUE);
  $field_storage_definitions['changed']->setRevisionable(TRUE);

  $field_storage_definitions['revision_id'] = BaseFieldDefinition::create('integer')
    ->setName('revision_id')
    ->setTargetEntityTypeId('group')
    ->setTargetBundle(NULL)
    ->setLabel(new TranslatableMarkup('Revision ID'))
    ->setReadOnly(TRUE)
    ->setSetting('unsigned', TRUE);
  $field_storage_definitions['revision_default'] = BaseFieldDefinition::create('boolean')
    ->setName('revision_default')
    ->setTargetEntityTypeId('group')
    ->setTargetBundle(NULL)
    ->setLabel(new TranslatableMarkup('Default revision'))
    ->setDescription(new TranslatableMarkup('A flag indicating whether this was a default revision when it was saved.'))
    ->setStorageRequired(TRUE)
    ->setInternal(TRUE)
    ->setTranslatable(FALSE)
    ->setRevisionable(TRUE);
  $field_storage_definitions['revision_translation_affected'] = BaseFieldDefinition::create('boolean')
    ->setName('revision_translation_affected')
    ->setTargetEntityTypeId('group')
    ->setTargetBundle(NULL)
    ->setLabel(new TranslatableMarkup('Revision translation affected'))
    ->setDescription(new TranslatableMarkup('Indicates if the last edit of a translation belongs to current revision.'))
    ->setReadOnly(TRUE)
    ->setRevisionable(TRUE)
    ->setTranslatable(TRUE);

  $field_storage_definitions['revision_created'] = BaseFieldDefinition::create('created')
    ->setName('revision_created')
    ->setTargetEntityTypeId('group')
    ->setTargetBundle(NULL)
    ->setLabel(new TranslatableMarkup('Revision create time'))
    ->setDescription(new TranslatableMarkup('The time that the current revision was created.'))
    ->setRevisionable(TRUE);
  $field_storage_definitions['revision_user'] = BaseFieldDefinition::create('entity_reference')
    ->setName('revision_user')
    ->setTargetEntityTypeId('group')
    ->setTargetBundle(NULL)
    ->setLabel(new TranslatableMarkup('Revision user'))
    ->setDescription(new TranslatableMarkup('The user ID of the author of the current revision.'))
    ->setSetting('target_type', 'user')
    ->setRevisionable(TRUE);
  $field_storage_definitions['revision_log_message'] = BaseFieldDefinition::create('string_long')
    ->setName('revision_log_message')
    ->setTargetEntityTypeId('group')
    ->setTargetBundle(NULL)
    ->setLabel(new TranslatableMarkup('Revision log message'))
    ->setDescription(new TranslatableMarkup('Briefly describe the changes you have made.'))
    ->setRevisionable(TRUE)
    ->setDefaultValue('');

  $definition_update_manager->updateFieldableEntityType($entity_type, $field_storage_definitions, $sandbox);

  return new TranslatableMarkup('Groups have been converted to be revisionable.');
}
