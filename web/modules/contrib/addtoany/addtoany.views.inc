<?php

/**
 * @file
 * Views integration for AddToAny.
 */

/**
 * Implementation of hook_views_data_alter().
 */
function addtoany_views_data_alter(array &$data) {
  // Add the addtoany link to the node fields
  $data['node']['addtoany_share'] = array(
    'title' => t('AddToAny share buttons'),
    'field' => array(
      'title' => t('AddToAny share buttons'),
      'help' => t('Provide share buttons for sharing the content.'),
      'id' => 'node_addtoany_share',
    ),
  );
}
