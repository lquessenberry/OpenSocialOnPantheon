<?php

/**
 * @file
 * Post update functions for Group.
 */

use Drupal\group\Entity\GroupType;
use Drupal\group\Entity\GroupContentType;

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
