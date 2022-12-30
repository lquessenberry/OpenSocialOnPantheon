<?php

namespace Drupal\bootstrap\Plugin\Form;

use Drupal\bootstrap\Bootstrap;
use Drupal\bootstrap\Plugin\Setting\DeprecatedSettingInterface;
use Drupal\bootstrap\Utility\Element;
use Drupal\Core\Form\FormStateInterface;

/**
 * Implements hook_form_system_theme_settings_alter().
 *
 * @ingroup plugins_form
 * @ingroup plugins_setting
 *
 * @BootstrapForm("system_theme_settings")
 */
class SystemThemeSettings extends FormBase implements FormInterface {

  /**
   * {@inheritdoc}
   */
  public function alterFormElement(Element $form, FormStateInterface $form_state, $form_id = NULL) {
    $theme = $this->getTheme($form, $form_state);
    if (!$theme) {
      return;
    }

    // Creates the necessary groups (vertical tabs) for a Bootstrap based theme.
    $this->createGroups($form, $form_state);

    // Iterate over all setting plugins and add them to the form.
    foreach ($theme->getSettingPlugin() as $setting) {
      // Skip settings that shouldn't be created automatically.
      if (!$setting->autoCreateFormElement()) {
        continue;
      }
      $setting->alterForm($form->getArray(), $form_state);
    }
  }

  /**
   * Sets up the vertical tab groupings.
   *
   * @param \Drupal\bootstrap\Utility\Element $form
   *   The Element object that comprises the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  protected function createGroups(Element $form, FormStateInterface $form_state) {
    // Vertical tabs for global settings provided by core or contrib modules.
    if (!isset($form['global'])) {
      $form['global'] = [
        '#type' => 'vertical_tabs',
        '#weight' => -9,
        '#prefix' => '<h2><small>' . t('Override Global Settings') . '</small></h2>',
      ];
    }

    // Iterate over existing children and move appropriate ones to global group.
    foreach ($form->children() as $child) {
      if ($child->isType(['details', 'fieldset']) && !$child->hasProperty('group')) {
        $child->setProperty('type', 'details');
        $child->setProperty('group', 'global');
      }
    }

    // Provide the necessary default groups.
    $form['bootstrap'] = [
      '#type' => 'vertical_tabs',
      '#attached' => ['library' => ['bootstrap/theme-settings']],
      '#prefix' => '<h2><small>' . t('Bootstrap Settings') . '</small></h2>',
      '#weight' => -10,
    ];
    $groups = [
      'general' => t('General'),
      'components' => t('Components'),
      'javascript' => t('JavaScript'),
      'cdn' => t('CDN'),
      'advanced' => t('Advanced'),
    ];
    foreach ($groups as $group => $title) {
      $form[$group] = [
        '#type' => 'details',
        '#title' => $title,
        '#group' => 'bootstrap',
      ];

      // Show a button to reset cached HTTP requests.
      if ($group === 'advanced') {
        $cache = \Drupal::keyValueExpirable('theme:' . $this->theme->getName() . ':http');
        $count = count($cache->getAll());
        $form[$group]['reset_http_request_cache'] = [
          '#type' => 'item',
          '#title' => $this->t('Cached HTTP requests: @count', ['@count' => $count]),
          '#weight' => 100,
          '#smart_description' => FALSE,
          '#description' => $this->t('All external HTTP requests initiated by this theme are subject to caching. Cacheability is determined automatically based on a manually passed TTL value by the initiator or if there is a "max-age" response header present. These cached requests will persist through cache rebuilds and will only be requested again once they have expired. If you believe there is some request not being properly retrieved, you can manually reset this cache here.'),
          '#description_display' => 'before',
          '#prefix' => '<div id="reset-http-request-cache">',
          '#suffix' => '</div>',
        ];

        $form[$group]['reset_http_request_cache']['submit'] = [
          '#type' => 'submit',
          '#value' => $this->t('Reset HTTP Request Cache'),
          '#description' => $this->t('Note: this will not reset any cached CDN data; see "Advanced Cache" in the "CDN" section.'),
          '#prefix' => '<div>',
          '#suffix' => '</div>',
          '#submit' => [
            [get_class($this), 'submitResetHttpRequestCache'],
          ],
          '#ajax' => [
            'callback' => [get_class($this), 'ajaxResetHttpRequestCache'],
            'wrapper' => 'reset-http-request-cache',
          ],
        ];
      }
    }
  }

  /**
   * Submit callback for resetting the cached HTTP requests.
   *
   * @param array $form
   *   Nested array of form elements that comprise the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public static function submitResetHttpRequestCache(array $form, FormStateInterface $form_state) {
    $form_state->setRebuild();
    $theme = SystemThemeSettings::getTheme(Element::create($form), $form_state);
    $cache = \Drupal::keyValueExpirable('theme:' . $theme->getName() . ':http');
    $cache->deleteAll();
  }

  /**
   * AJAX callback for reloading the cached HTTP request markup.
   *
   * @param array $form
   *   Nested array of form elements that comprise the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public static function ajaxResetHttpRequestCache(array $form, FormStateInterface $form_state) {
    return $form['advanced']['reset_http_request_cache'];
  }

  /**
   * Retrieves the currently selected theme on the settings form.
   *
   * @param \Drupal\bootstrap\Utility\Element $form
   *   The Element object that comprises the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return \Drupal\bootstrap\Theme|false
   *   The currently selected theme object or FALSE if not a Bootstrap theme.
   */
  public static function getTheme(Element $form, FormStateInterface $form_state) {
    $build_info = $form_state->getBuildInfo();
    $theme = isset($build_info['args'][0]) ? Bootstrap::getTheme($build_info['args'][0]) : FALSE;

    // Do not continue if the theme is not Bootstrap specific.
    if (!$theme || !$theme->isBootstrap()) {
      unset($form['#submit'][0]);
      unset($form['#validate'][0]);
    }

    return $theme;
  }

  /**
   * {@inheritdoc}
   */
  public static function submitFormElement(Element $form, FormStateInterface $form_state) {
    $theme = self::getTheme($form, $form_state);
    if (!$theme) {
      return;
    }

    $cache_tags = [];
    $save = FALSE;
    $settings = $theme->settings();
    $rebuild_cdn_assets = FALSE;

    // Iterate over all setting plugins and manually save them since core's
    // process is severely limiting and somewhat broken.
    foreach ($theme->getSettingPlugin() as $name => $setting) {
      // Skip saving deprecated settings.
      if ($setting instanceof DeprecatedSettingInterface) {
        $form_state->unsetValue($name);
        continue;
      }

      // Allow the setting to participate in the form submission process.
      // Must call the "submitForm" method in case any setting actually uses it.
      // It should, in turn, invoke "submitFormElement", if the setting that
      // overrides it is implemented properly.
      $setting->submitForm($form->getArray(), $form_state);

      // Retrieve the submitted value.
      $value = $form_state->getValue($name);

      // Trim any new lines and convert to simple new line breaks.
      $definition = $setting->getPluginDefinition();
      if (isset($definition['type']) && $definition['type'] === 'textarea' && is_string($value)) {
        $value = implode("\n", array_filter(array_map('trim', preg_split("/\r\n|\n/", $value))));
      }

      // Determine if the setting has a new value that overrides the original.
      // Ignore the schemas "setting" because it's handled by UpdateManager.
      if ($name !== 'schemas' && $settings->overridesValue($name, $value)) {
        // Set the new value.
        $settings->set($name, $value);

        // Retrieve the cache tags for the setting.
        $cache_tags = array_unique(array_merge($setting->getCacheTags()));

        // A CDN setting has changed, flag that CDN assets should be rebuilt.
        if (strpos($name, 'cdn') === 0) {
          $rebuild_cdn_assets = TRUE;
        }

        // Flag the save.
        $save = TRUE;
      }

      // Remove value from the form state object so core doesn't re-save it.
      $form_state->unsetValue($name);
    }

    // Save the settings, if needed.
    if ($save) {
      // Remove any cached CDN assets so they can be rebuilt.
      if ($rebuild_cdn_assets) {
        $settings->clear('cdn_cache');
      }

      $settings->save();

      // Invalidate necessary cache tags.
      if ($cache_tags) {
        \Drupal::service('cache_tags.invalidator')->invalidateTags($cache_tags);
      }

      // Clear our internal theme cache so it can be rebuilt properly.
      $theme->getCache('settings')->deleteAll();
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function validateFormElement(Element $form, FormStateInterface $form_state) {
    $theme = self::getTheme($form, $form_state);
    if (!$theme) {
      return;
    }

    // Iterate over all setting plugins and allow them to participate.
    foreach ($theme->getSettingPlugin() as $setting) {
      // Allow the setting to participate in the form validation process.
      // Must call the "validateForm" method in case any setting actually uses
      // it. It should, in turn, invoke "validateFormElement", if the setting
      // that overrides it is implemented properly.
      $setting->validateForm($form->getArray(), $form_state);
    }
  }

}
