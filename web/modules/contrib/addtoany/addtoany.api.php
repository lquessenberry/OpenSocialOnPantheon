<?php

/**
 * @file
 * Hooks specific to the AddToAny module.
 */

/**
 * Alter the entity types that the AddToAny pseudo-field is available for.
 *
 * @param array $types
 *   The entity types.
 */
function hook_addtoany_entity_types_alter(&$types) {
  // Add the "taxonomy_term" entity type.
  $types[] = 'taxonomy_term';
}

/**
 * @} End of "addtogroup addtoany".
 */
