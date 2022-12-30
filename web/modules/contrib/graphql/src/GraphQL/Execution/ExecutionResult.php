<?php

namespace Drupal\graphql\GraphQL\Execution;

use GraphQL\Executor\ExecutionResult as LibraryExecutionResult;
use Drupal\Core\Cache\CacheableDependencyInterface;
use Drupal\Core\Cache\RefinableCacheableDependencyTrait;

/**
 * Expand the upstream ExecutionResult to make it Drupal cachable.
 */
class ExecutionResult extends LibraryExecutionResult implements CacheableDependencyInterface {
  use RefinableCacheableDependencyTrait;

  /**
   * PHP Serialization: skip some class members when serializing during tests.
   */
  public function __sleep(): array {
    // PHPUnit error: Fatal error: Uncaught Exception: Serialization of
    // 'Closure' is not allowed.
    // Remove some closure-containing members before serializing.
    $vars = get_object_vars($this);
    unset($vars['extensions']);
    return array_keys($vars);
  }

}
