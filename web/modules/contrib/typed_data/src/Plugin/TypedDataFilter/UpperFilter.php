<?php

namespace Drupal\typed_data\Plugin\TypedDataFilter;

use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\TypedData\DataDefinition;
use Drupal\Core\TypedData\DataDefinitionInterface;
use Drupal\Core\TypedData\Type\StringInterface;
use Drupal\typed_data\DataFilterBase;

/**
 * A data filter which changes a string to upper case.
 *
 * @DataFilter(
 *   id = "upper",
 *   label = @Translation("The upper filter converts the entire input string to upper case."),
 * )
 */
class UpperFilter extends DataFilterBase {

  /**
   * {@inheritdoc}
   */
  public function canFilter(DataDefinitionInterface $definition) {
    return is_subclass_of($definition->getClass(), StringInterface::class);
  }

  /**
   * {@inheritdoc}
   */
  public function filtersTo(DataDefinitionInterface $definition, array $arguments) {
    return DataDefinition::create('string');
  }

  /**
   * {@inheritdoc}
   */
  public function filter(DataDefinitionInterface $definition, $value, array $arguments, BubbleableMetadata $bubbleable_metadata = NULL) {
    return mb_strtoupper($value);
  }

}
