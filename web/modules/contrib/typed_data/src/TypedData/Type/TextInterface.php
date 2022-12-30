<?php

namespace Drupal\typed_data\TypedData\Type;

use Drupal\Core\TypedData\Type\StringInterface;

/**
 * Interface for text data.
 *
 * The plain value of a string is a regular PHP string. For setting the value
 * any PHP variable that casts to a string may be passed.
 *
 * This type adds nothing to the base StringInterface type. It is used as a
 * indicator that the string data is 'long'. The Typed Data API module may
 * then use a Textarea form widget for editing the value.
 *
 * @ingroup typed_data
 */
interface TextInterface extends StringInterface {
  // Simply extends StringInterface.
}
