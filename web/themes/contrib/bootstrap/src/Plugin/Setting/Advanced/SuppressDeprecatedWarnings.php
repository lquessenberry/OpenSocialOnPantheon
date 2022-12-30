<?php

namespace Drupal\bootstrap\Plugin\Setting\Advanced;

use Drupal\bootstrap\Plugin\Setting\SettingBase;

/**
 * The "suppress_deprecated_warnings" theme setting.
 *
 * @ingroup plugins_setting
 *
 * @BootstrapSetting(
 *   id = "suppress_deprecated_warnings",
 *   type = "checkbox",
 *   weight = -2,
 *   title = @Translation("Suppress deprecated warnings"),
 *   defaultValue = 0,
 *   description = @Translation("Enable this setting if you wish to suppress deprecated warning messages."),
 *   groups = {
 *     "advanced" = @Translation("Advanced"),
 *   },
 * )
 */
class SuppressDeprecatedWarnings extends SettingBase {}
