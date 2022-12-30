<?php

namespace Drupal\bootstrap\Plugin\Setting;

use Drupal\bootstrap\DeprecatedInterface;

/**
 * Interface DeprecatedInterface.
 */
interface DeprecatedSettingInterface extends DeprecatedInterface, SettingInterface {

  /**
   * The setting that replaces the deprecated setting.
   *
   * @return \Drupal\bootstrap\Plugin\Setting\SettingInterface
   *   The replacement setting.
   */
  public function getDeprecatedReplacementSetting();

}
