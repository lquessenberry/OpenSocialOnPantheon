<?php

/**
 * @file
 * Hooks specific to the Group module.
 */

use Drupal\group\Entity\GroupInterface;

/**
 * @addtogroup hooks
 * @{
 */

/**
 * Alter the links for the group_operations block.
 *
 * @param array $operations
 *   A list of links to be set in an 'operations' element.
 * @param GroupInterface $group
 *   The group to alter the operations for.
 *
 * @see \Drupal\group\Plugin\Block\GroupOperationsBlock
 * @see \Drupal\Core\Render\Element\Dropbutton
 */
function hook_group_operations_alter(array &$operations, GroupInterface $group) {
  if ($group->label() == 'Hotel California') {
    unset($operations['group-leave']);
  }
}

/**
 * Provide entity types in which entities can be linked to the group.
 *
 * @return array
 *   An associative array of statuses that shows if additional fields per each
 *   group type should be created, keyed by entity type identifier.
 *
 * @see group_entity_base_field_info()
 */
function hook_group_types() {
  return [
    'node' => TRUE,
    'user' => FALSE,
  ];
}

/**
 * Alter entity types in which entities can be linked to the group.
 *
 * @param array $types
 *   An associative array of entity types returned by hook_group_types().
 *
 * @see group_entity_base_field_info()
 */
function hook_group_types_alter(array &$types) {
  if (isset($types['user']) && !$types['user']) {
    $types['user'] = TRUE;
  }
}

/**
 * @} End of "addtogroup hooks".
 */
