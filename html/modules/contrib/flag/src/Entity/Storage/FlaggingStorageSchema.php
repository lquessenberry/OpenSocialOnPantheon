<?php

namespace Drupal\flag\Entity\Storage;

use Drupal\Core\Entity\ContentEntityTypeInterface;
use Drupal\Core\Entity\Sql\SqlContentEntityStorageSchema;

/**
 * Defines the flag schema handler.
 */
class FlaggingStorageSchema extends SqlContentEntityStorageSchema {

  /**
   * {@inheritdoc}
   */
  protected function getEntitySchema(ContentEntityTypeInterface $entity_type, $reset = FALSE) {
    $schema = parent::getEntitySchema($entity_type, $reset);

    $schema['flagging']['indexes'] += array(
      'entity_id__uid' => array('entity_id', 'uid'),
    );

    return $schema;
  }

}
