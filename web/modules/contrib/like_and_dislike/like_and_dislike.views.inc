<?php

/**
 * @file
 * Provide views data for the module.
 */

/**
 * Implements hook_views_data_alter().
 */
function like_and_dislike_views_data_alter(array &$data) {
  $entity_definitions = \Drupal::entityTypeManager()->getDefinitions();
  foreach ($entity_definitions as $entity_type_id => $entity_type) {
    // Only display content entities with a view builder.
    if ($entity_type->getGroup() != 'content' || !$entity_type->hasHandlerClass('view_builder')) {
      continue;
    }
    $data[$entity_type_id]['like_and_dislike'] = [
      'title' => t('Like and Dislike'),
      'field' => [
        'title' => t('Like and Dislike'),
        'help' => t('Like and dislike widget.'),
        'id' => 'like_and_dislike',
      ],
    ];
  }
}
