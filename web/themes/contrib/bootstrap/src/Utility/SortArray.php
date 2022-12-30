<?php

namespace Drupal\bootstrap\Utility;

use Drupal\Component\Utility\SortArray as CoreSortArray;

/**
 * Extends \Drupal\Component\Utility\SortArray.
 *
 * @ingroup utility
 */
class SortArray extends CoreSortArray {

  /**
   * {@inheritdoc}
   */
  public static function sortByKeyString($a, $b, $key) {
    $aString = Unicode::castToString(is_array($a) && isset($a[$key]) ? $a[$key] : '');
    $bString = Unicode::castToString(is_array($b) && isset($b[$key]) ? $b[$key] : '');
    return strnatcasecmp($aString, $bString);
  }

}
