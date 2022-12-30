<?php

namespace Drupal\bootstrap\Plugin\Setting\Advanced\Cdn;

use Drupal\bootstrap\Plugin\Setting\DeprecatedSettingInterface;

/**
 * Due to BC reasons, this class cannot be moved.
 *
 * @todo Move namespace up one.
 */

/**
 * The "cdn_custom_css_min" theme setting.
 *
 * @BootstrapSetting(
 *   id = "cdn_custom_css_min",
 *   type = "textfield",
 *   weight = 2,
 *   title = @Translation("Minified Bootstrap CSS URL"),
 *   defaultValue = "https://cdn.jsdelivr.net/npm/bootstrap@3.4.1/dist/css/bootstrap.min.css",
 *   description = @Translation("Additionally, you can provide the minimized version of the file. It will be used instead if site aggregation is enabled."),
 *   groups = {
 *     "cdn" = @Translation("CDN (Content Delivery Network)"),
 *     "cdn_provider" = false,
 *     "custom" = false,
 *   },
 * )
 *
 * @deprecated since 8.x-3.18. Replaced with new setting. Will be removed in a
 *   future release.
 *
 * @see \Drupal\bootstrap\Plugin\Setting\Advanced\Cdn\CdnCustom
 */
class CdnCustomCssMin extends CdnProviderBase implements DeprecatedSettingInterface {

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
    return '\Drupal\bootstrap\Plugin\Setting\Advanced\Cdn\CdnCustom';
  }

  /**
   * {@inheritdoc}
   */
  public function getDeprecatedReplacementSetting() {
    return $this->theme->getSettingPlugin('cdn_custom');
  }

  /**
   * {@inheritdoc}
   */
  public function getDeprecatedVersion() {
    return '8.x-3.18';
  }

}
