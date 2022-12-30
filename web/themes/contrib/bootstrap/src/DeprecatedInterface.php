<?php

namespace Drupal\bootstrap;

/**
 * Interface DeprecatedInterface.
 */
interface DeprecatedInterface {

  /**
   * The reason for deprecation.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   A TranslatableMarkup object.
   */
  public function getDeprecatedReason();

  /**
   * The code that replaces the deprecated functionality.
   *
   * @return string|false
   *   The replacement code location or FALSE if there is no replacement.
   */
  public function getDeprecatedReplacement();

  /**
   * The version this was deprecated in.
   *
   * @return string
   *   A version string.
   */
  public function getDeprecatedVersion();

}
