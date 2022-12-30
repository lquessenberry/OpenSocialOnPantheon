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
 * The "cdn_theme" theme setting.
 *
 * @ingroup plugins_setting
 *
 * @BootstrapSetting(
 *   id = "cdn_theme",
 *   type = "select",
 *   weight = 3,
 *   title = @Translation("Theme"),
 *   description = @Translation("Choose a theme provided by the CDN Provider."),
 *   groups = {
 *     "cdn" = @Translation("CDN (Content Delivery Network)"),
 *     "cdn_provider" = false,
 *   },
 * )
 */
class CdnTheme extends CdnProviderBase {

  /**
   * {@inheritdoc}
   */
  public function alterFormElement(Element $form, FormStateInterface $form_state, $form_id = NULL) {
    $setting = $this->getSettingElement($form, $form_state);
    $setting->setProperty('suffix', '<div id="bootstrap-theme-preview"></div>');

    // Immediately return if the provider doesn't support themes.
    $provider = $this->getProvider();
    if (!$provider->supportsThemes()) {
      $setting->access(FALSE);
      return;
    }

    $version = $form_state->getValue('cdn_version', $this->theme->getSetting('cdn_version'));

    $exceptions = $provider->trackCdnExceptions(function () use ($provider, $setting, $version) {
      $options = [];
      $themes = $provider->getCdnThemes($version);
      foreach ($themes as $theme => $assets) {
        $options[ucfirst($assets->getLibrary())][$theme] = $assets->getLabel();
      }

      $setting->setProperty('options', $options);
    });

    // Check for any CDN failure(s).
    if ($exceptions) {
      $setting->setError($this->t('Unable to parse the @provider API to determine available themes.', [
        '@provider' => $provider->getLabel(),
      ]));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function processDeprecatedValues(array $values, array $deprecated) {
    // @todo Remove deprecated setting support in a future release.
    $deprecated = "cdn_{$this->getProvider()->getPluginId()}_theme";
    return isset($values[$deprecated]) ? $values[$deprecated] : NULL;
  }

}
