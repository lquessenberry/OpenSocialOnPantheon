<?php

namespace Drupal\bootstrap\Plugin\Setting\Advanced\Cdn;

use Drupal\bootstrap\Plugin\Provider\ProviderInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * Due to BC reasons, this class cannot be moved.
 *
 * @todo Move namespace up one.
 */

/**
 * The "cdn_cache_ttl_library" theme setting.
 *
 * @ingroup plugins_setting
 *
 * @BootstrapSetting(
 *   id = "cdn_cache_ttl_library",
 *   type = "select",
 *   weight = 4,
 *   title = @Translation("Theme Library Alterations"),
 *   description = @Translation("The length of time to cache the theme's library alterations before rebuilding them again. Note: any change to CDN values automatically triggers a new build."),
 *   defaultValue = \Drupal\bootstrap\Plugin\Provider\ProviderInterface::TTL_FOREVER,
 *   groups = {
 *     "cdn" = @Translation("CDN (Content Delivery Network)"),
 *     "cdn_provider" = false,
 *     "cache" = @Translation("Advanced Cache"),
 *   },
 * )
 */
class CdnCacheTtlLibrary extends CdnCacheTtlBase {

  /**
   * {@inheritdoc}
   */
  protected function getSettingValue(FormStateInterface $form_state) {
    return $this->getProvider()->getCacheTtl(ProviderInterface::CACHE_LIBRARY);
  }

}
