<?php

namespace Drupal\typed_data\Plugin\DataType;

use Drupal\Core\TypedData\Plugin\DataType\StringData;
use Drupal\typed_data\TypedData\Type\TextInterface;

/**
 * The text data type.
 *
 * The text data type differs from the string data type in that text is assumed
 * to be 'long'. This is the same nomenclature used in the Drupal Configuration
 * API - strings are short pieces of text, while text is long. In practical
 * use, the only difference is that string data uses a textfield form widget
 * while text data uses a textarea form widget which is more appropriate for
 * the assumed length of the data.
 *
 * The plain value of a text type is a regular PHP string. For setting the value
 * any PHP variable that casts to a string may be passed.
 *
 * @DataType(
 *   id = "text",
 *   label = @Translation("Text")
 * )
 */
class TextData extends StringData implements TextInterface {
  // Simply extends StringData.
}
