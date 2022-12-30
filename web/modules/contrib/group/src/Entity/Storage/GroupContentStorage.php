<?php

namespace Drupal\group\Entity\Storage;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Entity\Sql\SqlContentEntityStorage;
use Drupal\group\Entity\GroupInterface;

/**
 * Defines the storage handler class for group content entities.
 *
 * This extends the base storage class, adding required special handling for
 * loading group content entities based on group and plugin information.
 */
class GroupContentStorage extends SqlContentEntityStorage implements GroupContentStorageInterface {

  /**
   * Static cache for looking up group content entities for entities.
   *
   * @var array
   */
  protected $loadByEntityCache = [];

  /**
   * {@inheritdoc}
   */
  public function createForEntityInGroup(ContentEntityInterface $entity, GroupInterface $group, $plugin_id, $values = []) {
    // An unsaved entity cannot have any group content.
    if ($entity->id() === NULL) {
      throw new EntityStorageException("Cannot add an unsaved entity to a group.");
    }

    // An unsaved group cannot have any content.
    if ($group->id() === NULL) {
      throw new EntityStorageException("Cannot add an entity to an unsaved group.");
    }

    // Check whether the entity can actually be added to the group.
    $plugin = $group->getGroupType()->getContentPlugin($plugin_id);
    if ($entity->getEntityTypeId() != $plugin->getEntityTypeId()) {
      throw new EntityStorageException("Invalid plugin provided for adding the entity to the group.");
    }

    // Verify the bundle as well if the plugin is specific about them.
    $supported_bundle = $plugin->getEntityBundle();
    if ($supported_bundle !== FALSE) {
      if ($entity->bundle() != $supported_bundle) {
        throw new EntityStorageException("The provided plugin provided does not support the entity's bundle.");
      }
    }

    // Set the necessary keys for a valid GroupContent entity.
    $keys = [
      'type' => $plugin->getContentTypeConfigId(),
      'gid' => $group->id(),
      'entity_id' => $entity->id(),
    ];

    // Return an unsaved GroupContent entity.
    return $this->create($keys + $values);
  }

  /**
   * {@inheritdoc}
   */
  public function loadByGroup(GroupInterface $group, $plugin_id = NULL, $filters = []) {
    // An unsaved group cannot have any content.
    if ($group->id() === NULL) {
      throw new EntityStorageException("Cannot load GroupContent entities for an unsaved group.");
    }

    $properties = ['gid' => $group->id()] + $filters;

    // If a plugin ID was provided, set the group content type ID for it.
    if (isset($plugin_id)) {
      /** @var \Drupal\group\Plugin\GroupContentEnablerInterface $plugin */
      $plugin = $group->getGroupType()->getContentPlugin($plugin_id);
      $properties['type'] = $plugin->getContentTypeConfigId();
    }

    return $this->loadByProperties($properties);
  }

  /**
   * {@inheritdoc}
   */
  public function loadByEntity(ContentEntityInterface $entity) {
    // An unsaved entity cannot have any group content.
    $entity_id = $entity->id();
    if ($entity_id === NULL) {
      throw new EntityStorageException("Cannot load GroupContent entities for an unsaved entity.");
    }

    $entity_type_id = $entity->getEntityTypeId();
    if (!isset($this->loadByEntityCache[$entity_type_id][$entity_id])) {
      /** @var \Drupal\group\Entity\Storage\GroupContentTypeStorageInterface $storage */
      $storage = $this->entityTypeManager->getStorage('group_content_type');
      $group_content_types = $storage->loadByEntityTypeId($entity_type_id);

      // Statically cache all group content IDs for the group content types.
      if (!empty($group_content_types)) {
        // Use an optimized plain query to avoid the overhead of entity and SQL
        // query builders.
        $query = "SELECT id from {{$this->dataTable}} WHERE entity_id = :entity_id AND type IN (:types[])";
        $this->loadByEntityCache[$entity_type_id][$entity_id] = $this->database
          ->query($query, [
            ':entity_id' => $entity_id,
            ':types[]' => array_keys($group_content_types),
          ])
          ->fetchCol();
      }
      // If no responsible group content types were found, we return nothing.
      else {
        $this->loadByEntityCache[$entity_type_id][$entity_id] = [];
      }
    }

    if (!empty($this->loadByEntityCache[$entity_type_id][$entity_id])) {
      return $this->loadMultiple($this->loadByEntityCache[$entity_type_id][$entity_id]);
    }
    else {
      return [];
    }
  }

  /**
   * {@inheritdoc}
   */
  public function loadByContentPluginId($plugin_id) {
    // If no responsible group content types were found, we return nothing.
    /** @var \Drupal\group\Entity\Storage\GroupContentTypeStorageInterface $storage */
    $storage = $this->entityTypeManager->getStorage('group_content_type');
    $group_content_types = $storage->loadByContentPluginId($plugin_id);
    if (empty($group_content_types)) {
      return [];
    }

    return $this->loadByProperties(['type' => array_keys($group_content_types)]);
  }

  /**
   * {@inheritdoc}
   */
  public function resetCache(array $ids = NULL) {
    parent::resetCache($ids);
    $this->loadByEntityCache = [];
  }

}
