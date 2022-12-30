<?php

namespace Drupal\group\Entity\Views;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\views\EntityViewsData;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides the views data for the group content entity type.
 */
class GroupContentViewsData extends EntityViewsData {

  /**
   * The group content enabler plugin manager.
   *
   * @var \Drupal\group\Plugin\GroupContentEnablerManagerInterface
   */
  protected $pluginManager;

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    /** @var static $views_data */
    $views_data = parent::createInstance($container, $entity_type);
    $views_data->pluginManager = $container->get('plugin.manager.group_content_enabler');
    return $views_data;
  }

  /**
   * {@inheritdoc}
   */
  public function getViewsData() {
    $data = parent::getViewsData();

    // Add a custom numeric argument for the parent group ID that allows us to
    // use replacement titles with the parent group's label.
    $data['group_content_field_data']['gid']['argument'] = [
      'id' => 'group_id',
      'numeric' => TRUE,
    ];

    // Get the data table for GroupContent entities.
    $data_table = $this->entityType->getDataTable();

    // Unset the 'entity_id' field relationship as we want a more powerful one.
    // @todo Eventually, we may want to replace all of 'entity_id'.
    unset($data[$data_table]['entity_id']['relationship']);

    /** @var \Drupal\Core\Entity\EntityTypeInterface[] $entity_types */
    $entity_types = $this->entityTypeManager->getDefinitions();

    // Add views data for all defined plugins so modules can provide default
    // views even though their plugins may not have been installed yet.
    foreach ($this->pluginManager->getAll() as $plugin) {
      $entity_type_id = $plugin->getEntityTypeId();
      if (!isset($entity_types[$entity_type_id])) {
        continue;
      }
      $entity_type = $entity_types[$entity_type_id];
      $entity_data_table = $entity_type->getDataTable() ?: $entity_type->getBaseTable();

      // Create a unique field name for this views field.
      $field_name = 'gc__' . $entity_type_id;

      // We only add one 'group_content' relationship per entity type.
      if (isset($data[$entity_data_table][$field_name])) {
        continue;
      }

      $t_args = [
        '@entity_type' => $entity_type->getLabel(),
      ];

      // This relationship will allow a group content entity to easily map to a
      // content entity that it ties to a group, optionally filtering by plugin.
      $data[$data_table][$field_name] = [
        'title' => $this->t('@entity_type from group content', $t_args),
        'help' => $this->t('Relates to the @entity_type entity the group content represents.', $t_args),
        'relationship' => [
          'group' => $entity_type->getLabel(),
          'base' => $entity_data_table,
          'base field' => $entity_type->getKey('id'),
          'relationship field' => 'entity_id',
          'id' => 'group_content_to_entity',
          'label' => $this->t('Group content @entity_type', $t_args),
          'target_entity_type' => $entity_type_id,
        ],
      ];
    }

    return $data;
  }

}
