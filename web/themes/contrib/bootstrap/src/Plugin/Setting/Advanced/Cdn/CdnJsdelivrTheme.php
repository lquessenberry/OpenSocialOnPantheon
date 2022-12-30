<?php

namespace Drupal\bootstrap\Plugin\Setting\Advanced\Cdn;

/**
 * Due to BC reasons, this class cannot be moved.
 *
 * @todo Move namespace up one.
 */

use Drupal\bootstrap\Plugin\Setting\DeprecatedSettingInterface;

/**
 * The "cdn_jsdelivr_theme" theme setting.
 *
 * @BootstrapSetting(
 *   cdn_provider = "jsdelivr",
 *   id = "cdn_jsdelivr_theme",
 *   type = "select",
 *   title = @Translation("Theme"),
 *   description = @Translation("Choose the Example Theme provided by Bootstrap or one of the Bootswatch themes."),
 *   defaultValue = "bootstrap",
 *   empty_option = @Translation("Default"),
 *   empty_value = "bootstrap",
 *   groups = {
 *     "cdn" = @Translation("CDN (Content Delivery Network)"),
 *     "cdn_provider" = false,
 *     "jsdelivr" = false,
 *   },
 * )
 *
 * @deprecated since 8.x-3.18. Replaced with new setting. Will be removed in a
 *   future release.
 *
 * @see \Drupal\bootstrap\Plugin\Setting\Advanced\Cdn\CdnTheme
 */
class CdnJsdelivrTheme extends CdnProviderBase implements DeprecatedSettingInterface {

  /**
   * {@inheritdoc}
   */
  public function getDeprecatedReason() {
    return $this->t('Replaced with new setting. Will be removed in a future release.');
  }

  /**
   * {@inheritdoc}
   */
  public function getDeprecatedReplacement() {
    return '\Drupal\bootstrap\Plugin\Setting\Advanced\Cdn\CdnTheme';
  }

  /**
   * {@inheritdoc}
   */
  public function getDeprecatedReplacementSetting() {
    return $this->theme->getSettingPlugin('cdn_theme');
  }

  /**
   * {@inheritdoc}
   */
  public function getDeprecatedVersion() {
    return '8.x-3.18';
  }

}
