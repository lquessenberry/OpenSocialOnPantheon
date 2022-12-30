<?php

namespace Drupal\group\Fields;

use Drupal\Core\Field\EntityReferenceFieldItemList;
use Drupal\Core\TypedData\ComputedItemListTrait;
use Drupal\Core\TypedData\DataDefinitionInterface;
use Drupal\Core\TypedData\TypedDataInterface;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\group\Entity\Group;
use Drupal\group\Entity\GroupContent;

/**
 * A computed property for the related groups.
 */
class GroupGroupReferenceItemList extends EntityReferenceFieldItemList {

  // Support non-database views. Ex: Search API Solr.
  use DependencySerializationTrait;
  use ComputedItemListTrait;

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
  public function computeValue() {
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

  /**
   * @inheritDoc
   */
  public function postSave($update) {
    if ($this->valueComputed) {
      $node = $this->getEntity();

      // Index-Array for wanted groups ( gid => gid )
      $gids_wanted = [];
      foreach ($this->list as $delta => $item) {
        $id = $item->get('target_id')->getValue();
        $gids_wanted[$id] = $id;
      }

      // Index-Array for existing groups for this node gid => gid
      $gids_existing = [];

      // Index-Array for gnodes for easier deletion gid => GroupContent
      $gnodes_existing = [];

      /** @var \Drupal\group\Entity\Storage\GroupContentStorageInterface $storage */
      $storage = \Drupal::entityTypeManager()->getStorage('group_content');
      // Loads all groups with a relation to the node
      $activeGroupListEntity = $storage->loadByEntity($node);
      foreach ($activeGroupListEntity as $groupContent) {
        // fill Index-Array with existing groups gid => gid
        $gids_existing[$groupContent->getGroup()->id()] = $groupContent->getGroup()->id();

        // fill Index-Array for existing gnodes
        $gnodes_existing[$groupContent->getGroup()->id()] = $groupContent;
      }

      // Union for existing and wanted groups
      $gids_union = $gids_existing + $gids_wanted;

      // Index-Array gnodes to create
      // = (Union for existing and wanted) minus existing
      $gids_create = array_diff($gids_union, $gids_existing);

      // Index-Array gnodes to delete
      // = (Union for existing and wanted) minus wanted
      $gids_delete = array_diff($gids_union, $gids_wanted);

      foreach ($gids_create as $gid) {
        // Skip -none- option
        if ($gid == '_none') {
          continue;
        }
        $group = Group::load($gid);
        /** @var \Drupal\group\Plugin\GroupContentEnablerInterface $plugin */
        $plugin = $group->getGroupType()->getContentPlugin('group_node:'.$node->bundle());
        $group_content = GroupContent::create([
          'type' => $plugin->getContentTypeConfigId(),
          'gid' => $group->id(),
          'entity_id' => $node->id(),
        ]);
        $group_content->save();
      }

      foreach ($gids_delete as $gid) {
        // Skip -none- option
        if ($gid == '_none') {
          continue;
        }
        $gnodes_existing[$gid]->delete();
      }
    }
    return parent::postSave($update);

    // @fixme Also care that we got correct referencable entities in widget.
  }

}
