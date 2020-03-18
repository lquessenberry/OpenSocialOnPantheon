<?php

namespace Drupal\block_field;

use \Drupal\Core\Block\BlockManagerInterface;

/**
 * Defines a service that manages block plugins for the block field.
 */
class BlockFieldManager implements BlockFieldManagerInterface {

  /**
   * The block plugin manager.
   *
   * @var \Drupal\Core\Block\BlockManagerInterface
   */
  protected $blockManager;

  /**
   * Constructs a new BlockFieldManager.
   *
   * @param \Drupal\Core\Block\BlockManagerInterface $block_manager
   *   The block plugin manager.
   */
  public function __construct(BlockManagerInterface $block_manager) {
    $this->blockManager = $block_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function getBlockDefinitions() {
    $definitions = $this->blockManager->getSortedDefinitions();
    $block_definitions = [];
    foreach ($definitions as $plugin_id => $definition) {
      // Context aware plugins are not currently supported.
      // Core and component plugins can be context-aware
      // https://www.drupal.org/node/1938688
      // @see \Drupal\ctools\Plugin\Block\EntityView
      if (isset($definition['context'])) {
        continue;
      }

      $block_definitions[$plugin_id] = $definition;
    }
    return $block_definitions;
  }

}
