<?php

namespace Drupal\group_test_plugin\Plugin\GroupContentEnabler;

use Drupal\node\Entity\NodeType;
use Drupal\Component\Plugin\Derivative\DeriverBase;

class NodeAsContentDeriver extends DeriverBase {

  /**
   * {@inheritdoc}.
   */
  public function getDerivativeDefinitions($base_plugin_definition) {
    $this->derivatives = [];

    $this->derivatives['page'] = [
      'entity_bundle' => 'page',
      'label' => t('Pages as content'),
      'description' => t('Adds pages to groups.'),
    ] + $base_plugin_definition;

    $this->derivatives['article'] = [
      'entity_bundle' => 'article',
      'label' => t('Article as content'),
      'description' => t('Adds articles to groups.'),
    ] + $base_plugin_definition;

    return $this->derivatives;
  }

}
