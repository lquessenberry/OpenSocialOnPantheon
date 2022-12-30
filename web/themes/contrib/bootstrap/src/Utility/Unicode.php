<?php

namespace Drupal\bootstrap\Utility;

use Drupal\bootstrap\Bootstrap;
use Drupal\Component\Utility\Unicode as CoreUnicode;
use Drupal\Component\Utility\Xss;

/**
 * Extends \Drupal\Component\Utility\Unicode.
 *
 * @ingroup utility
 */
class Unicode extends CoreUnicode {

  /**
   * Casts a value to a string, recursively if an array.
   *
   * @param mixed $value
   *   Any value.
   * @param string $delimiter
   *   The delimiter to use when joining multiple items in an array.
   *
   * @return string
   *   The cast string.
   */
  public static function castToString($value = NULL, $delimiter = '.') {
    if (is_object($value) && method_exists($value, '__toString')) {
      return (string) ($value->__toString() ?: '');
    }
    if (is_array($value)) {
      foreach ($value as $key => $item) {
        $value[$key] = static::castToString($item, $delimiter);
      }
      return implode($delimiter, array_filter($value));
    }
    // Handle scalar values.
    if (isset($value) && is_scalar($value) && !is_bool($value)) {
      return (string) $value;
    }
    return '';
  }

  /**
   * Extracts the hook name from a function name.
   *
   * @param string $string
   *   The function name to extract the hook name from.
   * @param string $suffix
   *   A suffix hook ending (like "alter") to also remove.
   * @param string $prefix
   *   A prefix hook beginning (like "form") to also remove.
   *
   * @return string
   *   The extracted hook name.
   */
  public static function extractHook($string, $suffix = NULL, $prefix = NULL) {
    $regex = '^(' . implode('|', array_keys(Bootstrap::getTheme()->getAncestry())) . ')';
    $regex .= $prefix ? '_' . $prefix : '';
    $regex .= $suffix ? '_|_' . $suffix . '$' : '';
    return preg_replace("/$regex/", '', $string);
  }

  /**
   * Converts a callback to a string representation.
   *
   * @param array|string $callback
   *   The callback to convert.
   * @param bool $array
   *   Flag determining whether or not to convert the callback to an array.
   *
   * @return string
   *   The converted callback as a string or an array if $array is specified.
   *
   * @see \Drupal\bootstrap\Bootstrap::addCallback()
   */
  public static function convertCallback($callback, $array = FALSE) {
    if (is_array($callback)) {
      if (is_object($callback[0])) {
        $callback[0] = get_class($callback[0]);
      }
      $callback = implode('::', $callback);
    }
    if ($callback[0] === '\\') {
      $callback = mb_substr($callback, 1);
    }
    if ($array && mb_substr($callback, '::') !== FALSE) {
      $callback = explode('::', $callback);
    }
    return $callback;
  }

  /**
   * Escapes a delimiter in a string.
   *
   * Note: this is primarily useful in situations where dot notation is used
   * where the values also contain dots, like in a semantic version string.
   *
   * @param string $string
   *   The string to search in.
   * @param string $delimiter
   *   The delimiter to escape.
   *
   * @return string
   *   The escaped string.
   *
   * @see \Drupal\bootstrap\Utility\Unicode::splitDelimiter()
   */
  public static function escapeDelimiter($string, $delimiter = '.') {
    return str_replace($delimiter, "\\$delimiter", $string);
  }

  /**
   * Determines if a string of text is considered "simple".
   *
   * @param string $string
   *   The string of text to check "simple" criteria on.
   * @param int|false $length
   *   The length of characters used to determine whether or not $string is
   *   considered "simple". Set explicitly to FALSE to disable this criteria.
   * @param array|false $allowed_tags
   *   An array of allowed tag elements. Set explicitly to FALSE to disable this
   *   criteria.
   * @param bool $html
   *   A variable, passed by reference, that indicates whether or not the
   *   string contains HTML.
   *
   * @return bool
   *   Returns TRUE if the $string is considered "simple", FALSE otherwise.
   */
  public static function isSimple($string, $length = 250, $allowed_tags = NULL, &$html = FALSE) {
    // Typecast to a string (if an object).
    $string_clone = (string) $string;

    // Use the advanced drupal_static() pattern.
    static $drupal_static_fast;
    if (!isset($drupal_static_fast)) {
      $drupal_static_fast['strings'] = &drupal_static(__METHOD__);
    }
    $strings = &$drupal_static_fast['strings'];
    if (!isset($strings[$string_clone])) {
      $plain_string = strip_tags($string_clone);
      $simple = TRUE;
      if ($allowed_tags !== FALSE) {
        $filtered_string = Xss::filter($string_clone, $allowed_tags);
        $html = $filtered_string !== $plain_string;
        $simple = $simple && $string_clone === $filtered_string;
      }
      if ($length !== FALSE) {
        $simple = $simple && strlen($plain_string) <= intval($length);
      }
      $strings[$string_clone] = $simple;
    }
    return $strings[$string_clone];
  }

  /**
   * Splits a string by a specified delimiter, allowing them to be escaped.
   *
   * Note: this is primarily useful in situations where dot notation is used
   * where the values also contain dots, like in a semantic version string.
   *
   * @param string $string
   *   The string to split into parts.
   * @param string $delimiter
   *   The delimiter used to split the string.
   * @param bool $escapable
   *   Flag indicating whether the $delimiter can be escaped using a backward
   *   slash (\).
   *
   * @return array
   *   An array of strings, split where the specified $delimiter was present.
   *
   * @see \Drupal\bootstrap\Utility\Unicode::escapeDelimiter()
   * @see https://stackoverflow.com/a/6243797
   */
  public static function splitDelimiter($string, $delimiter = '.', $escapable = TRUE) {
    if (!$escapable) {
      return explode($delimiter, $string);
    }

    // Split based on delimiter.
    $parts = preg_split('~\\\\' . preg_quote($delimiter, '~') . '(*SKIP)(*FAIL)|\.~s', $string);

    // Iterate over the parts and remove backslashes from delimiters.
    return array_map(function ($string) use ($delimiter) {
      return str_replace("\\$delimiter", $delimiter, $string);
    }, $parts);
  }

  /**
   * Finds the position of the first occurrence of a string in another string.
   *
   * @param string $haystack
   *   The string to search in.
   * @param string $needle
   *   The string to find in $haystack.
   * @param int $offset
   *   If specified, start the search at this number of characters from the
   *   beginning (default 0).
   *
   * @return int|false
   *   The position where $needle occurs in $haystack, always relative to the
   *   beginning (independent of $offset), or FALSE if not found. Note that
   *   a return value of 0 is not the same as FALSE.
   *
   * @deprecated in bootstrap:8.x-3.22 and is removed from bootstrap:5.0.0.
   *   Use mb_strpos() instead.
   * @see https://www.drupal.org/project/bootstrap/issues/3096963
   *
   * @see https://www.drupal.org/node/2850048
   */
  public static function strpos($haystack, $needle, $offset = 0) {
    @trigger_error('\Drupal\bootstrap\Utility\Unicode::strpos() is deprecated in bootstrap:8.x-3.22 and will be removed before bootstrap:5.0.0. Use mb_strpos() instead. See https://www.drupal.org/project/bootstrap/issues/3096963.', E_USER_DEPRECATED);
    if (static::getStatus() == static::STATUS_MULTIBYTE) {
      return mb_strpos($haystack, $needle, $offset);
    }
    else {
      // Remove Unicode continuation characters, to be compatible with
      // Unicode::strlen() and Unicode::substr().
      $haystack = preg_replace("/[\x80-\xBF]/", '', $haystack);
      $needle = preg_replace("/[\x80-\xBF]/", '', $needle);
      return strpos($haystack, $needle, $offset);
    }
  }

  /**
   * Converts a UTF-8 string to lowercase.
   *
   * @param string $text
   *   The string to run the operation on.
   *
   * @return string
   *   The string in lowercase.
   *
   * @deprecated in bootstrap:8.x-3.22 and is removed from bootstrap:5.0.0.
   *   Use mb_strtolower() instead.
   * @see https://www.drupal.org/project/bootstrap/issues/3096963
   *
   * @see https://www.drupal.org/node/2850048
   */
  public static function strtolower($text) {
    @trigger_error('\Drupal\bootstrap\Utility\Unicode::strtolower() is deprecated in bootstrap:8.x-3.22 and will be removed before bootstrap:5.0.0. Use mb_strtolower() instead. See https://www.drupal.org/project/bootstrap/issues/3096963.', E_USER_DEPRECATED);
    if (static::getStatus() == static::STATUS_MULTIBYTE) {
      return mb_strtolower($text);
    }
    else {
      // Use C-locale for ASCII-only lowercase.
      $text = strtolower($text);
      // Case flip Latin-1 accented letters.
      $text = preg_replace_callback('/\xC3[\x80-\x96\x98-\x9E]/', '\Drupal\Component\Utility\Unicode::caseFlip', $text);
      return $text;
    }
  }

  /**
   * Cuts off a piece of a string based on character indices and counts.
   *
   * Follows the same behavior as PHP's own substr() function. Note that for
   * cutting off a string at a known character/substring location, the usage of
   * PHP's normal strpos/substr is safe and much faster.
   *
   * @param string $text
   *   The input string.
   * @param int $start
   *   The position at which to start reading.
   * @param int $length
   *   The number of characters to read.
   *
   * @return string
   *   The shortened string.
   *
   * @deprecated in bootstrap:8.x-3.22 and is removed from bootstrap:5.0.0.
   *   Use mb_substr() instead.
   * @see https://www.drupal.org/project/bootstrap/issues/3096963
   *
   * @see https://www.drupal.org/node/2850048
   */
  public static function substr($text, $start, $length = NULL) {
    @trigger_error('\Drupal\bootstrap\Utility\Unicode::substr() is deprecated in bootstrap:8.x-3.22 and will be removed before bootstrap:5.0.0. Use mb_substr() instead. See https://www.drupal.org/project/bootstrap/issues/3096963.', E_USER_DEPRECATED);
    if (static::getStatus() == static::STATUS_MULTIBYTE) {
      return $length === NULL ? mb_substr($text, $start) : mb_substr($text, $start, $length);
    }
    else {
      $strlen = strlen($text);
      // Find the starting byte offset.
      $bytes = 0;
      if ($start > 0) {
        // Count all the characters except continuation bytes from the start
        // until we have found $start characters or the end of the string.
        $bytes = -1;
        $chars = -1;
        while ($bytes < $strlen - 1 && $chars < $start) {
          $bytes++;
          $c = ord($text[$bytes]);
          if ($c < 0x80 || $c >= 0xC0) {
            $chars++;
          }
        }
      }
      elseif ($start < 0) {
        // Count all the characters except continuation bytes from the end
        // until we have found abs($start) characters.
        $start = abs($start);
        $bytes = $strlen;
        $chars = 0;
        while ($bytes > 0 && $chars < $start) {
          $bytes--;
          $c = ord($text[$bytes]);
          if ($c < 0x80 || $c >= 0xC0) {
            $chars++;
          }
        }
      }
      $istart = $bytes;

      // Find the ending byte offset.
      if ($length === NULL) {
        $iend = $strlen;
      }
      elseif ($length > 0) {
        // Count all the characters except continuation bytes from the starting
        // index until we have found $length characters or reached the end of
        // the string, then backtrace one byte.
        $iend = $istart - 1;
        $chars = -1;
        $last_real = FALSE;
        while ($iend < $strlen - 1 && $chars < $length) {
          $iend++;
          $c = ord($text[$iend]);
          $last_real = FALSE;
          if ($c < 0x80 || $c >= 0xC0) {
            $chars++;
            $last_real = TRUE;
          }
        }
        // Backtrace one byte if the last character we found was a real
        // character and we don't need it.
        if ($last_real && $chars >= $length) {
          $iend--;
        }
      }
      elseif ($length < 0) {
        // Count all the characters except continuation bytes from the end
        // until we have found abs($start) characters, then backtrace one byte.
        $length = abs($length);
        $iend = $strlen;
        $chars = 0;
        while ($iend > 0 && $chars < $length) {
          $iend--;
          $c = ord($text[$iend]);
          if ($c < 0x80 || $c >= 0xC0) {
            $chars++;
          }
        }
        // Backtrace one byte if we are not at the beginning of the string.
        if ($iend > 0) {
          $iend--;
        }
      }
      else {
        // $length == 0, return an empty string.
        return '';
      }

      return substr($text, $istart, max(0, $iend - $istart + 1));
    }
  }

}
