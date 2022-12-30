<?php

namespace Drupal\group_test_plugin\Plugin\GroupContentEnabler;

use Drupal\group\Plugin\GroupContentEnablerBase;

/**
 * Provides a content enabler for nodes.
 *
 * @GroupContentEnabler(
 *   id = "node_as_content",
 *   label = @Translation("Node as content"),
 *   description = @Translation("Adds nodes to groups."),
 *   entity_type_id = "node",
 *   entity_access = TRUE,
 *   deriver = "Drupal\group_test_plugin\Plugin\GroupContentEnabler\NodeAsContentDeriver",
 *   handlers = {
 *     "access" = "Drupal\group\Plugin\GroupContentAccessControlHandler",
 *     "permission_provider" = "Drupal\group_test_plugin\Plugin\GroupContentEnabler\FullEntityPermissionProvider",
 *   },
 *   admin_permission = "administer node_as_content:page"
 * )
 */
class NodeAsContent extends GroupContentEnablerBase {
}
