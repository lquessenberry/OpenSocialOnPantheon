<?php

namespace Drupal\typed_data_global_context_test\ContextProvider;

use Drupal\Core\Plugin\Context\Context;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\Plugin\Context\ContextProviderInterface;

/**
 * Provides a global context about dragons for testing purposes.
 *
 * @group typed_data
 */
class SimpleTestContext implements ContextProviderInterface {

  /**
   * {@inheritdoc}
   */
  public function getRuntimeContexts(array $unqualified_context_ids) {
    $context_definition = new ContextDefinition('string');
    $context = new Context($context_definition, 'Dragons are better than unicorns!');

    return [
      'dragons' => $context,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getAvailableContexts() {
    return $this->getRuntimeContexts([]);
  }

}
