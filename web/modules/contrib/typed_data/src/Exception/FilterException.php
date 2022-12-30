<?php

namespace Drupal\typed_data\Exception;

/**
 * Exception thrown when filters cannot be applied.
 *
 * Data filters should provide separate exception classes for any possible
 * problem.
 */
abstract class FilterException extends TypedDataException {}
