<?php

namespace Drupal\bootstrap\Plugin\Setting\Advanced\Cdn;

use Drupal\bootstrap\Plugin\Form\SystemThemeSettings;
use Drupal\bootstrap\Plugin\ProviderManager;
use Drupal\bootstrap\Utility\Element;
use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Access\AccessResultAllowed;
use Drupal\Core\Form\FormStateInterface;

/**
 * Due to BC reasons, this class cannot be moved.
 *
 * @todo Move namespace up one.
 */

/**
 * The "cdn_custom" theme setting.
 *
 * @ingroup plugins_setting
 *
 * @BootstrapSetting(
 *   id = "cdn_custom",
 *   type = "textarea",
 *   description = @Translation("One complete URL per line. All URLs are validated and parsed to determine available version(s) and/or theme(s). A URL can be any file ending in <code>.css</code> or <code>.js</code> (with matching response MIME type). Minified URLs can also be supplied and the will be used automatically."),
 *   groups = {
 *     "cdn" = @Translation("CDN (Content Delivery Network)"),
 *     "cdn_provider" = false,
 *     "custom" = @Translation("Custom URLs"),
 *   },
 * )
 */
class CdnCustom extends CdnProviderBase {

  /**
   * {@inheritdoc}
   */
  public function access() {
    return parent::access()->andIf(AccessResultAllowed::allowedIf($this->getProvider()->getPluginId() === 'custom'));
  }

  /**
   * {@inheritdoc}
   */
  public function alterFormElement(Element $form, FormStateInterface $form_state, $form_id = NULL) {
    $group = $this->getGroupElement($form, $form_state);
    $group->setProperty('weight', 99);
    $group->access($this->access());

    $setting = $this->getSettingElement($form, $form_state);
    $setting->setProperty('smart_description', FALSE);

    $rows = count(array_filter(array_map('trim', preg_split("/\r\n|\n/", $form_state->getValue('cdn_custom', '')))));
    $setting->setProperty('rows', $rows > 20 ? 20 : $rows);

    $group->apply = $this->setCdnProvidersAjax(Element::createStandalone([
      '#weight' => 100,
      '#type' => 'submit',
      '#value' => $this->t('Apply'),
      '#submit' => [
        [get_class($this), 'submitApplyCss'],
      ],
    ]));
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultValue() {
    return implode("\n", [
      'https://cdn.jsdelivr.net/npm/bootstrap@3.4.1/dist/css/bootstrap.css',
      'https://cdn.jsdelivr.net/npm/bootstrap@3.4.1/dist/css/bootstrap.min.css',
      'https://cdn.jsdelivr.net/npm/bootstrap@3.4.1/dist/js/bootstrap.js',
      'https://cdn.jsdelivr.net/npm/bootstrap@3.4.1/dist/js/bootstrap.min.js',
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function processDeprecatedValues(array $values, array $deprecated) {
    // Merge the deprecated settings together to form a new line for each value.
    // @todo Remove deprecated setting support in a future release.
    return implode("\n", $values) ?: NULL;
  }

  /**
   * Submit callback for resetting CDN Provider cache.
   *
   * @param array $form
   *   Nested array of form elements that comprise the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public static function submitApplyCss(array $form, FormStateInterface $form_state) {
    $theme = SystemThemeSettings::getTheme(Element::create($form, $form_state), $form_state);
    $theme->setSetting('cdn_custom', $form_state->getValue('cdn_custom'));
    $form_state->setRebuild();
  }

  /**
   * {@inheritdoc}
   */
  public static function validateFormElement(Element $form, FormStateInterface $form_state) {
    // Immediately return if this isn't the currently selected CDN Provider.
    if ($form_state->getValue('cdn_provider') !== 'custom') {
      return;
    }

    $theme = SystemThemeSettings::getTheme($form, $form_state);

    /** @var \Drupal\bootstrap\Plugin\Provider\Custom $provider */
    $provider = ProviderManager::load($theme, 'custom');

    $urls = array_filter(array_map('trim', preg_split("/\r\n|\n/", $form_state->getValue('cdn_custom', ''))));

    $invalid = [];
    foreach ($urls as $url) {
      try {
        $provider->validateUrl($url);
      }
      catch (\Exception $e) {
        $invalid[] = $e->getMessage();
      }
    }
    if ($invalid) {
      $form_state->setErrorByName('cdn_custom', t('Invalid Custom URLs: <ul><li>@invalid</li></ul>', [
        '@invalid' => new FormattableMarkup(implode('</li><li>', $invalid), []),
      ]));

      // Unfortunately, any errors set during validation prevents the form
      // rebuilding functionality from working. This has to be changed here.
      $form->cdn->cdn_provider->custom->setProperty('open', TRUE);
    }
  }

}
