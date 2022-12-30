<?php

namespace Drupal\group\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\CacheableMetadata;

/**
 * Provides a block with operations the user can perform on a group.
 *
 * @Block(
 *   id = "group_operations",
 *   admin_label = @Translation("Group operations"),
 *   context_definitions = {
 *     "group" = @ContextDefinition("entity:group", required = FALSE)
 *   }
 * )
 */
class GroupOperationsBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    $build = [];

    // The operations available in this block vary per the current user's group
    // permissions. It obviously also varies per group, but we cannot know for
    // sure how we got that group as it is up to the context provider to
    // implement that. This block will then inherit the appropriate cacheable
    // metadata from the context, as set by the context provider.
    $cacheable_metadata = new CacheableMetadata();
    $cacheable_metadata->setCacheContexts(['user.group_permissions']);

    /** @var \Drupal\group\Entity\GroupInterface $group */
    if (($group = $this->getContextValue('group')) && $group->id()) {
      $links = [];

      // Retrieve the operations and cacheable metadata from the installed
      // content plugins.
      foreach ($group->getGroupType()->getInstalledContentPlugins() as $plugin) {
        /** @var \Drupal\group\Plugin\GroupContentEnablerInterface $plugin */
        $links += $plugin->getGroupOperations($group);
        $cacheable_metadata = $cacheable_metadata->merge($plugin->getGroupOperationsCacheableMetadata());
      }

      if ($links) {
        // Allow modules to alter the collection of gathered links.
        \Drupal::moduleHandler()->alter('group_operations', $links, $group);

        // Sort the operations by weight.
        uasort($links, '\Drupal\Component\Utility\SortArray::sortByWeightElement');

        // Create an operations element with all of the links.
        $build['#type'] = 'operations';
        $build['#links'] = $links;
      }
    }

    // Set the cacheable metadata on the build.
    $cacheable_metadata->applyTo($build);

    return $build;
  }

}
