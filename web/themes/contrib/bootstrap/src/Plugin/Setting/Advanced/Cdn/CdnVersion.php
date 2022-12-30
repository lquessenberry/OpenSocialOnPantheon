<?php

namespace Drupal\bootstrap\Plugin\Setting\Advanced\Cdn;

/**
 * Due to BC reasons, this class cannot be moved.
 *
 * @todo Move namespace up one.
 */

use Drupal\bootstrap\Utility\Element;
use Drupal\Core\Form\FormStateInterface;

/**
 * The "cdn_version" theme setting.
 *
 * @ingroup plugins_setting
 *
 * @BootstrapSetting(
 *   id = "cdn_version",
 *   type = "select",
 *   weight = 2,
 *   title = @Translation("Version"),
 *   description = @Translation("Choose a version provided by the CDN Provider."),
 *   defaultValue = \Drupal\bootstrap\Bootstrap::FRAMEWORK_VERSION,
 *   groups = {
 *     "cdn" = @Translation("CDN (Content Delivery Network)"),
 *     "cdn_provider" = false,
 *   },
 * )
 */
class CdnVersion extends CdnProviderBase {

  /**
   * {@inheritdoc}
   */
  public function alterFormElement(Element $form, FormStateInterface $form_state, $form_id = NULL) {
    $setting = $this->setCdnProvidersAjax($this->getSettingElement($form, $form_state));

    // Immediately return if the provider doesn't support versions.
    $provider = $this->getProvider();
    if (!$provider->supportsVersions()) {
      $setting->access(FALSE);
      return;
    }

    $setting->setProperty('description', $this->t('These versions are automatically populated by the @provider API.', [
      '@provider' => $provider->getLabel(),
    ]));

    $exceptions = $provider->trackCdnExceptions(function () use ($provider, $setting) {
      $setting->setProperty('options', $provider->getCdnVersions());
    });

    // Check for tracked CDN exceptions.
    if ($exceptions) {
      $setting->setError($this->t('Unable to parse the @provider API to determine available versions.', [
        '@provider' => $provider->getLabel(),
      ]));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function processDeprecatedValues(array $values, array $deprecated) {
    // @todo Remove deprecated setting support in a future release.
    $deprecated = "cdn_{$this->getProvider()->getPluginId()}_version";
    return isset($values[$deprecated]) ? $values[$deprecated] : NULL;
  }

}
