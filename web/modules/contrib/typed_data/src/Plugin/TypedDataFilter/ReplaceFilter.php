<?php

namespace Drupal\typed_data\Plugin\TypedDataFilter;

use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\TypedData\DataDefinition;
use Drupal\Core\TypedData\DataDefinitionInterface;
use Drupal\Core\TypedData\Type\StringInterface;
use Drupal\typed_data\DataFilterBase;

/**
 * A data filter which substitutes text in a string.
 *
 * @DataFilter(
 *   id = "replace",
 *   label = @Translation("The replace filter substitutes the text given in the first argument with the text given in the second argument."),
 * )
 */
class ReplaceFilter extends DataFilterBase {

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
  public function allowsNullValues() {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function getNumberOfRequiredArguments() {
    return 2;
  }

  /**
   * {@inheritdoc}
   */
  public function validateArguments(DataDefinitionInterface $definition, array $arguments) {
    $errors = parent::validateArguments($definition, $arguments);
    foreach ($arguments as $arg) {
      // Ensure the provided value is given for this data.
      $violations = $this->getTypedDataManager()
        ->create($definition, $arg)
        ->validate();
      foreach ($violations as $violation) {
        $errors[] = $violation->getMessage();
      }
    }
    return $errors;
  }

  /**
   * {@inheritdoc}
   */
  public function filter(DataDefinitionInterface $definition, $value, array $arguments, BubbleableMetadata $bubbleable_metadata = NULL) {
    return str_replace($arguments[0], $arguments[1], $value);
  }

}
