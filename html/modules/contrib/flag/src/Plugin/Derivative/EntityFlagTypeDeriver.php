<?php

namespace Drupal\flag\Plugin\Derivative;

use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Core\Entity\ContentEntityTypeInterface;

/**
 * Derivative class for entity flag type plugin.
 */
class EntityFlagTypeDeriver extends DeriverBase {

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_def) {
    $derivatives = array();
    foreach (\Drupal::entityTypeManager()->getDefinitions() as $entity_id => $entity_type) {
      // Skip config entity types.
      if (!$entity_type instanceof ContentEntityTypeInterface) {
        continue;
      }
      $derivatives[$entity_id] = [
        'title' => $entity_type->getLabel(),
        'entity_type' => $entity_id,
      ] + $base_plugin_def;
    }

    return $derivatives;
  }

}
