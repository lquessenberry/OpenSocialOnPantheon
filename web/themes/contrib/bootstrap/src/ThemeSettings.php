<?php

namespace Drupal\bootstrap;

use Drupal\bootstrap\Plugin\Setting\DeprecatedSettingInterface;
use Drupal\Core\Theme\ThemeSettings as CoreThemeSettings;
use Drupal\Component\Utility\DiffArray;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Config\Config;
use Drupal\Core\Config\StorageException;

/**
 * Provides a configuration API wrapper for runtime merged theme settings.
 *
 * This is a wrapper around theme_get_setting() since it does not inherit
 * base theme config nor handle default/overridden values very well.
 *
 * @ingroup utility
 */
class ThemeSettings extends Config {

  /**
   * The default settings.
   *
   * @var array
   */
  protected $defaults;

  /**
   * A list of deprecated settings, keyed by the newer setting name.
   *
   * @var array
   */
  protected $deprecated;

  /**
   * The current theme object.
   *
   * @var \Drupal\bootstrap\Theme
   */
  protected $theme;

  /**
   * A list of available Setting plugins.
   *
   * @var \Drupal\bootstrap\Plugin\Setting\SettingInterface[]
   */
  protected $settings;

  /**
   * {@inheritdoc}
   */
  public function __construct(Theme $theme) {
    parent::__construct($theme->getName() . '.settings', \Drupal::service('config.storage'), \Drupal::service('event_dispatcher'), \Drupal::service('config.typed'));
    $this->theme = $theme;

    // Retrieve the available settings.
    $this->settings = $theme->getSettingPlugin();

    // Filter out the deprecated settings.
    /** @var \Drupal\bootstrap\Plugin\Setting\DeprecatedSettingInterface[] $deprecated */
    $deprecated = array_filter($this->settings, function ($setting) {
      return $setting instanceof DeprecatedSettingInterface;
    });

    $this->deprecated = [];
    foreach ($deprecated as $deprecatedName => $deprecatedSetting) {
      if (!is_null($deprecatedSetting)) {
        $key = $deprecatedSetting->getDeprecatedReplacementSetting()->getPluginId();
        if (!isset($this->deprecated[$key])) {
          $this->deprecated[$key] = [];
        }
        $this->deprecated[$key][$deprecatedName] = $deprecatedSetting;
      }
    }

    // Retrieve cache.
    $cache = $theme->getCache('settings');

    // Use cached settings.
    if ($defaults = $cache->get('defaults')) {
      $this->defaults = $defaults;
      $this->initWithData($cache->get('data', []));
      return;
    }

    // Retrieve the global settings from configuration.
    $this->defaults = \Drupal::config('system.theme.global')->get();

    // Retrieve the theme setting plugin discovery defaults (code).
    foreach ($this->settings as $name => $deprecatedSetting) {
      // Deprecated settings shouldn't provide default values, only set values.
      if ($deprecatedSetting instanceof DeprecatedSettingInterface) {
        continue;
      }
      $this->defaults[$name] = $deprecatedSetting->getDefaultValue();
    }

    // Retrieve the theme ancestry.
    $ancestry = $theme->getAncestry();

    // Remove the active theme from the ancestry.
    $active_theme = array_pop($ancestry);

    // Iterate and merge all ancestor theme config into the defaults.
    foreach ($ancestry as $ancestor) {
      $this->defaults = NestedArray::mergeDeepArray([$this->defaults, $this->getThemeConfig($ancestor)], TRUE);
    }

    // Merge the active theme config.
    $this->initWithData($this->getThemeConfig($active_theme, TRUE));

    // Cache the data and defaults.
    $cache->set('data', $this->data);
    $cache->set('defaults', $this->defaults);
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags() {
    return ['rendered'];
  }

  /**
   * {@inheritdoc}
   */
  public function get($key = '') {
    if (empty($key)) {
      return NestedArray::mergeDeepArray([$this->defaults, $this->data], TRUE);
    }
    else {
      $value = parent::get($key);
      if (!isset($value)) {
        $value = $this->getDeprecatedValue($key);
      }
      if (!isset($value)) {
        $value = $this->getOriginal($key);
      }
    }
    return $value;
  }

  /**
   * Retrieves a deprecated value from other setting(s).
   *
   * @param string $key
   *   The name of the setting to retrieve a deprecated value for.
   *
   * @return mixed
   *   A value from deprecated setting(s) or NULL if there is no deprecated
   *   value currently in use.
   */
  protected function getDeprecatedValue($key) {
    $value = NULL;

    // Immediately return if no deprecated settings to extract a values from.
    if (!isset($this->settings[$key]) || empty($this->deprecated[$key])) {
      return $value;
    }

    $setting = $this->settings[$key];
    $deprecatedSettings = $this->deprecated[$key];

    $values = [];
    foreach (array_keys($deprecatedSettings) as $deprecatedName) {
      $deprecatedValue = $this->get($deprecatedName);
      if (isset($deprecatedValue)) {
        $values[$deprecatedName] = $deprecatedValue;
      }
    }

    // Immediately return if there are no deprecated values to process.
    if (!$values) {
      return $value;
    }

    // Let the new setting handle how it should process the deprecated values.
    $value = $setting->processDeprecatedValues($values, $deprecatedSettings);

    // Deprecated value(s) found, show a message and then migrate them.
    if (isset($value)) {
      if (count($values) > 1) {
        Bootstrap::deprecated(NULL, NULL, t('The following theme settings have been deprecated and should no longer be used: "%deprecated". The values have been converted automatically for use with the supported theme setting "%name" instead. The configuration for the site should be exported to accommodate this change.', [
          '%deprecated' => implode('", "', array_keys($deprecatedSettings)),
          '%name' => $key,
        ]));
      }
      else {
        Bootstrap::deprecated(NULL, NULL, t('The following theme setting has been deprecated and should no longer be used: "%deprecated". The value has been converted automatically for use with the supported theme setting "%name" instead. The configuration for the site should be exported to accommodate this change.', [
          '%deprecated' => array_keys($deprecatedSettings)[0],
          '%name' => $key,
        ]));
      }
      $this->set($key, $value);
      foreach (array_keys($values) as $setting) {
        $this->clear($setting);
      }
      $this->save();
    }

    return $value;
  }

  /**
   * {@inheritdoc}
   */
  public function getOriginal($key = '', $apply_overrides = TRUE) {
    $original_data = $this->defaults;
    if ($apply_overrides) {
      // Apply overrides.
      if (isset($this->moduleOverrides) && is_array($this->moduleOverrides)) {
        $original_data = NestedArray::mergeDeepArray([$original_data, $this->moduleOverrides], TRUE);
      }
      if (isset($this->settingsOverrides) && is_array($this->settingsOverrides)) {
        $original_data = NestedArray::mergeDeepArray([$original_data, $this->settingsOverrides], TRUE);
      }
    }

    if (empty($key)) {
      return $original_data;
    }
    else {
      $parts = explode('.', $key);
      if (count($parts) == 1) {
        return isset($original_data[$key]) ? $original_data[$key] : NULL;
      }
      else {
        $value = NestedArray::getValue($original_data, $parts, $key_exists);
        return $key_exists ? $value : NULL;
      }
    }
  }

  /**
   * Retrieves a specific theme's stored config settings.
   *
   * @param \Drupal\bootstrap\Theme $theme
   *   A theme object.
   * @param bool $active_theme
   *   Flag indicating whether or not $theme is the active theme.
   *
   * @return array
   *   A array diff of overridden config theme settings.
   */
  public function getThemeConfig(Theme $theme, $active_theme = FALSE) {
    $config = new CoreThemeSettings($theme->getName());
    /** @var \Drupal\Core\File\FileUrlGeneratorInterface $file_url_generator */
    $file_url_generator = \Drupal::service('file_url_generator');

    // Retrieve configured theme-specific settings, if any.
    try {
      if ($theme_settings = \Drupal::config($theme->getName() . '.settings')->get()) {
        // Remove schemas if not the active theme.
        if (!$active_theme) {
          unset($theme_settings['schemas']);
        }
        $config->merge($theme_settings);
      }
    }
    catch (StorageException $e) {
    }

    // If the theme does not support a particular feature, override the
    // global setting and set the value to NULL.
    $info = $theme->getInfo();
    if (!empty($info['features'])) {
      foreach (_system_default_theme_features() as $feature) {
        if (!in_array($feature, $info['features'])) {
          $config->set('features.' . $feature, NULL);
        }
      }
    }

    // Generate the path to the logo image.
    if ($config->get('features.logo')) {
      $logo_url = FALSE;
      foreach (['svg', 'png', 'jpg'] as $type) {
        if (file_exists($theme->getPath() . "/logo.$type")) {
          $logo_url = $file_url_generator->generateString($theme->getPath() . "/logo.$type");
          break;
        }
      }
      if ($config->get('logo.use_default') && $logo_url) {
        $config->set('logo.url', $logo_url);
      }
      elseif (($logo_path = $config->get('logo.path')) && file_exists($logo_path)) {
        $config->set('logo.url', $file_url_generator->generateString($logo_path));
      }
    }

    // Generate the path to the favicon.
    if ($config->get('features.favicon')) {
      $favicon_url = $theme->getPath() . '/favicon.ico';
      if ($favicon_url && $config->get('favicon.use_default') && file_exists($favicon_url)) {
        $config->set('favicon.url', $file_url_generator->generateString($favicon_url));
      }
      else {
        $favicon_path = $config->get('favicon.path');
        if (file_exists($favicon_path)) {
          $config->set('favicon.url', $file_url_generator->generateString($favicon_path));
        }
      }
    }

    // Retrieve the config data.
    $data = $config->get();

    // Retrieve a diff of settings that override the defaults.
    $diff = DiffArray::diffAssocRecursive($data, $this->defaults);

    // Ensure core features and complex (array) settings are always present in
    // the diff. The theme settings form will not work properly otherwise.
    // @todo Just rebuild the features section of the form?
    $core_settings = ['favicon', 'features', 'logo'];
    $complex_settings = array_keys(array_filter($diff, 'is_array'));
    $all_complex_settings = array_unique(array_merge($core_settings, $complex_settings));

    foreach ($all_complex_settings as $key) {
      $arrays = [];
      $arrays[] = isset($this->defaults[$key]) ? $this->defaults[$key] : [];
      $arrays[] = isset($data[$key]) ? $data[$key] : [];
      $diff[$key] = NestedArray::mergeDeepArray($arrays, TRUE);
    }

    return $diff;
  }

  /**
   * Determines if a setting overrides the default value.
   *
   * @param string $name
   *   The name of the setting to check.
   * @param mixed $value
   *   The new value to check.
   *
   * @return bool
   *   TRUE or FALSE
   */
  public function overridesValue($name, $value) {
    // Retrieve the currently stored value for comparison purposes.
    $current_value = $this->get($name);

    // Due to the nature of DiffArray::diffAssocRecursive, if the provided
    // value is an empty array, it cannot be iterated over to determine if
    // the values are different. Instead, it must be checked explicitly.
    // @see https://www.drupal.org/node/2771121
    if ($value === [] && $current_value !== []) {
      return TRUE;
    }

    // Otherwise, determine if value is overridden by any array differences.
    return !!DiffArray::diffAssocRecursive([$name => $value], [$name => $current_value]);
  }

  /**
   * {@inheritdoc}
   */
  public function save($has_trusted_data = FALSE) {
    parent::save($has_trusted_data);
    $this->theme->getCache('settings')->deleteAll();
    return $this;
  }

}
