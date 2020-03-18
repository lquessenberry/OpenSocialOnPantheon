<?php

namespace Drupal\group\Fields;

use Drupal\Core\Field\EntityReferenceFieldItemList;
use Drupal\Core\TypedData\DataDefinitionInterface;
use Drupal\Core\TypedData\TypedDataInterface;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;

/**
 * A computed property for the related groups.
 */
class GroupGroupReferenceItemList extends EntityReferenceFieldItemList {

  // Support non-database views. Ex: Search API Solr.
  use DependencySerializationTrait;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  protected $entityTypeManager;

  /**
   * Constructs a GroupGroupReferenceItemList object.
   *
   * @param \Drupal\Core\TypedData\DataDefinitionInterface $definition
   *   The data definition.
   * @param string $name
   *   (optional) The name of the created property, or NULL if it is the root
   *   of a typed data tree. Defaults to NULL.
   * @param \Drupal\Core\TypedData\TypedDataInterface $parent
   *   (optional) The parent object of the data property, or NULL if it is the
   *   root of a typed data tree. Defaults to NULL.
   */
  public function __construct(DataDefinitionInterface $definition, $name = NULL, TypedDataInterface $parent = NULL) {
    parent::__construct($definition, $name, $parent);
    $this->entityTypeManager = \Drupal::service('entity_type.manager');
  }

  /**
   * {@inheritdoc}
   */
  public function get($index) {
    $this->computeValues();
    return isset($this->list[$index]) ? $this->list[$index] : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function referencedEntities() {
    $this->computeValues();
    return parent::referencedEntities();
  }

  /**
   * {@inheritdoc}
   */
  public function getIterator() {
    $this->computeValues();
    return parent::getIterator();
  }

  /**
   * {@inheritdoc}
   */
  public function computeValues() {
    // We only support nodes and users.
    if ($this->getEntity()->getEntityTypeId() === 'node') {
      $plugin_id = 'group_node:' . $this->getEntity()->bundle();
    }
    elseif ($this->getEntity()->getEntityTypeId() === 'user') {
      $plugin_id = 'group_membership';
    }
    else {
      return NULL;
    }

    // No value will exist if the entity has not been created so exit early.:wq
    if ($this->getEntity()->isNew()) {
      return NULL;
    }

    $handler_settings = $this->getItemDefinition()->getSetting('handler_settings');
    $group_types = isset($handler_settings['target_bundles']) ? $handler_settings['target_bundles'] : $this->entityTypeManager->getStorage('group_type')->loadMultiple();

    $group_content_types = $this->entityTypeManager->getStorage('group_content_type')->loadByProperties([
      'group_type' => array_keys($group_types),
      'content_plugin' => $plugin_id,
    ]);

    if (empty($group_content_types)) {
      return NULL;
    }

    $group_contents = $this->entityTypeManager->getStorage('group_content')->loadByProperties([
      'type' => array_keys($group_content_types),
      'entity_id' => $this->getEntity()->id(),
    ]);

    $this->list = [];
    if (!empty($group_contents)) {
      foreach ($group_contents as $delta => $group_content) {
        $this->list[] = $this->createItem($delta, [
          'target_id' => $group_content->gid->target_id,
        ]);
      }
    }
  }

}
