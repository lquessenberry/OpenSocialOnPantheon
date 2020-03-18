<?php

/**
 * @file
 * Views integration for AddToAny.
 */

/**
 * Implements hook_views_data_alter().
 */
function addtoany_views_data_alter(array &$data) {
  // Add the addtoany link to the node fields.
  $data['node']['addtoany_share'] = [
    'title' => t('AddToAny share buttons'),
    'field' => [
      'title' => t('AddToAny share buttons'),
      'help' => t('Provide share buttons for sharing the content.'),
      'id' => 'node_addtoany_share',
    ],
  ];
}
