<?php

namespace Drupal\typed_data\Plugin\TypedDataFilter;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\TypedData\EntityDataDefinitionInterface;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\TypedData\DataDefinition;
use Drupal\Core\TypedData\DataDefinitionInterface;
use Drupal\file\FileInterface;
use Drupal\typed_data\DataFilterBase;

/**
 * A data filter that provides the URL of an entity.
 *
 * @DataFilter(
 *   id = "entity_url",
 *   label = @Translation("Provides the URL of an entity."),
 * )
 */
class EntityUrlFilter extends DataFilterBase {

  /**
   * {@inheritdoc}
   */
  public function filter(DataDefinitionInterface $definition, $value, array $arguments, BubbleableMetadata $bubbleable_metadata = NULL) {
    assert($value instanceof EntityInterface);
    // EntityInterface::toUrl() does not work properly for File entities; this
    // is evidently "by design" and will not be fixed in core. Thus in order
    // for this filter to work with File entities we must treat them
    // differently, using the FileInterface::createFileUrl() method instead.
    // @see https://www.drupal.org/project/drupal/issues/2402533
    if ($value instanceof FileInterface) {
      // The FALSE argument creates an absolute URL.
      return $value->createFileUrl(FALSE);
    }
    else {
      return $value->toUrl('canonical', ['absolute' => TRUE])->toString();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function canFilter(DataDefinitionInterface $definition) {
    return $definition instanceof EntityDataDefinitionInterface;
  }

  /**
   * {@inheritdoc}
   */
  public function filtersTo(DataDefinitionInterface $definition, array $arguments) {
    return DataDefinition::create('uri');
  }

}
