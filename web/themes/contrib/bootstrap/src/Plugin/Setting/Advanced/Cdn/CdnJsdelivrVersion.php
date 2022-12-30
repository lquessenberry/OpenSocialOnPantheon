<?php

namespace Drupal\bootstrap\Plugin\Setting\Advanced\Cdn;

/**
 * Due to BC reasons, this class cannot be moved.
 *
 * @todo Move namespace up one.
 */

use Drupal\bootstrap\Plugin\Setting\DeprecatedSettingInterface;

/**
 * The "cdn_jsdelivr_version" theme setting.
 *
 * @BootstrapSetting(
 *   cdn_provider = "jsdelivr",
 *   id = "cdn_jsdelivr_version",
 *   type = "select",
 *   weight = -1,
 *   title = @Translation("Version"),
 *   description = @Translation("Choose the Bootstrap version from jsdelivr"),
 *   defaultValue = @BootstrapConstant("Drupal\bootstrap\Bootstrap::FRAMEWORK_VERSION"),
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
 * @see \Drupal\bootstrap\Plugin\Setting\Advanced\Cdn\CdnVersion
 */
class CdnJsdelivrVersion extends CdnProviderBase implements DeprecatedSettingInterface {

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
    return '\Drupal\bootstrap\Plugin\Setting\Advanced\Cdn\CdnVersion';
  }

  /**
   * {@inheritdoc}
   */
  public function getDeprecatedReplacementSetting() {
    return $this->theme->getSettingPlugin('cdn_version');
  }

  /**
   * {@inheritdoc}
   */
  public function getDeprecatedVersion() {
    return '8.x-3.18';
  }

}
