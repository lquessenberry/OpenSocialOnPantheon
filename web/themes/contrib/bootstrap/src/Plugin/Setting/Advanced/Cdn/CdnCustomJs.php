<?php

namespace Drupal\bootstrap\Plugin\Setting\Advanced\Cdn;

use Drupal\bootstrap\Plugin\Setting\DeprecatedSettingInterface;

/**
 * Due to BC reasons, this class cannot be moved.
 *
 * @todo Move namespace up one.
 */

/**
 * The "cdn_custom_js" theme setting.
 *
 * @BootstrapSetting(
 *   id = "cdn_custom_js",
 *   type = "textfield",
 *   weight = 3,
 *   title = @Translation("Bootstrap JavaScript URL"),
 *   defaultValue = "https://cdn.jsdelivr.net/npm/bootstrap@3.4.1/dist/js/bootstrap.js",
 *   description = @Translation("It is best to use <code>https</code> protocols here as it will allow more flexibility if the need ever arises."),
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
class CdnCustomJs extends CdnProviderBase implements DeprecatedSettingInterface {

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
