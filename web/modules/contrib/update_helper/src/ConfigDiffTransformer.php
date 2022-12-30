<?php

namespace Drupal\update_helper;

/**
 * Config transformer for configuration diffing.
 *
 * @package Drupal\update_helper
 */
class ConfigDiffTransformer {

  /**
   * Prefix to use to indicate config hierarchy.
   *
   * @var string
   *
   * @see ReversibleConfigDiffer::format().
   */
  protected $hierarchyPrefix = '::';

  /**
   * Prefix to use to indicate config values.
   *
   * @var string
   *
   * @see ReversibleConfigDiffer::format().
   */
  protected $valuePrefix = ' : ';

  /**
   * {@inheritdoc}
   */
  public function transform($config, $prefix = '') {
    $lines = [];

    $associative_config = array_keys($config) !== range(0, count($config) - 1);

    foreach ($config as $key => $value) {
      if (!$associative_config) {
        $key = '-';
      }

      $section_prefix = ($prefix) ? $prefix . $this->hierarchyPrefix . $key : $key;
      if (is_array($value) && !empty($value)) {
        $lines[] = $section_prefix;
        $new_lines = $this->transform($value, $section_prefix);
        foreach ($new_lines as $line) {
          $lines[] = $line;
        }
      }
      else {
        $lines[] = $section_prefix . $this->valuePrefix . $this->stringifyValue($value);
      }
    }

    return $lines;
  }

  /**
   * Reverse transformation of diff.
   *
   * @param array $config_string_lines
   *   String configuration lines.
   *
   * @return array
   *   Nested configuration array.
   */
  public function reverseTransform(array $config_string_lines) {
    $result = [];

    foreach ($config_string_lines as $row) {
      $key_value = explode(' : ', $row);

      $key_path = explode('::', $key_value[0]);

      $last_key = array_pop($key_path);
      $current_element = &$result;
      foreach ($key_path as $key) {
        if ($key === '-') {
          $key = count($current_element) - 1;
        }
        elseif (!isset($current_element[$key])) {
          $current_element[$key] = [];
        }

        $current_element = &$current_element[$key];
      }

      $value = [];
      if (count($key_value) === 2) {
        $value = $this->unstringifyValue($key_value[1]);
      }

      if ($last_key === '-') {
        $current_element[] = $value;
      }
      else {
        $current_element[$last_key] = $value;
      }

    }

    return $result;
  }

  /**
   * Get string representation of value in format that it can be un-serialized.
   *
   * @param mixed $value
   *   Value that should be serialized.
   *
   * @return string
   *   Return string representation of value.
   */
  protected function stringifyValue($value) {
    return serialize($value);
  }

  /**
   * Get correct value from string representation of it.
   *
   * @param string $value
   *   String value.
   *
   * @return mixed
   *   Returns value.
   */
  protected function unstringifyValue($value) {
    return unserialize($value);
  }

}
