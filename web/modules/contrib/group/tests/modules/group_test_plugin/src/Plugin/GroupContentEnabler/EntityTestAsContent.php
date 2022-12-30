<?php

namespace Drupal\group_test_plugin\Plugin\GroupContentEnabler;

use Drupal\group\Plugin\GroupContentEnablerBase;

/**
 * Provides a content enabler for test entities.
 *
 * @GroupContentEnabler(
 *   id = "entity_test_as_content",
 *   label = @Translation("Group test entity"),
 *   description = @Translation("Adds test entities to groups."),
 *   entity_type_id = "entity_test_with_owner",
 *   entity_access = TRUE,
 *   pretty_path_key = "entity_test_with_owner",
 *   reference_label = @Translation("Test entity"),
 *   reference_description = @Translation("The name of the test entity you want to add to the group"),
 *   handlers = {
 *     "access" = "Drupal\group\Plugin\GroupContentAccessControlHandler",
 *     "permission_provider" = "Drupal\group_test_plugin\Plugin\GroupContentEnabler\FullEntityPermissionProvider",
 *   },
 *   admin_permission = "administer entity_test_as_content"
 * )
 */
class EntityTestAsContent extends GroupContentEnablerBase {
}
