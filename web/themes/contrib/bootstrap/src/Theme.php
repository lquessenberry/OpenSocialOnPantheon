<?php

namespace Drupal\bootstrap;

use Drupal\bootstrap\Plugin\ProviderManager;
use Drupal\bootstrap\Plugin\SettingManager;
use Drupal\bootstrap\Plugin\UpdateManager;
use Drupal\bootstrap\Utility\Crypt;
use Drupal\bootstrap\Utility\Storage;
use Drupal\bootstrap\Utility\StorageItem;
use Drupal\Core\Extension\Extension;
use Drupal\Core\Extension\ThemeHandlerInterface;
use Drupal\Core\Site\Settings;
use Drupal\Core\Url;

/**
 * Defines a theme object.
 *
 * @ingroup utility
 */
class Theme {

  /**
   * Ignores the following directories during file scans of a theme.
   *
   * @see \Drupal\bootstrap\Theme::IGNORE_ASSETS
   * @see \Drupal\bootstrap\Theme::IGNORE_CORE
   * @see \Drupal\bootstrap\Theme::IGNORE_DOCS
   * @see \Drupal\bootstrap\Theme::IGNORE_DEV
   */
  const IGNORE_DEFAULT = -1;

  /**
   * Ignores the directories "assets", "css", "images" and "js".
   */
  const IGNORE_ASSETS = 0x1;

  /**
   * Ignores the directories "config", "lib" and "src".
   */
  const IGNORE_CORE = 0x2;

  /**
   * Ignores the directories "docs" and "documentation".
   */
  const IGNORE_DOCS = 0x4;

  /**
   * Ignores "bower_components", "grunt", "node_modules" and "starterkits".
   */
  const IGNORE_DEV = 0x8;

  /**
   * Ignores the directories "templates" and "theme".
   */
  const IGNORE_TEMPLATES = 0x16;

  /**
   * Flag indicating if the theme is Bootstrap based.
   *
   * @var bool
   */
  protected $bootstrap;

  /**
   * A list of available CDN Provider instances.
   *
   * @var \Drupal\bootstrap\Plugin\Provider\ProviderInterface[]
   */
  protected $cdnProviders;

  /**
   * Flag indicating if the theme is in "development" mode.
   *
   * @var bool
   *
   * This property can only be set via `settings.local.php`:
   *
   * @code
   * $settings['theme.dev'] = TRUE;
   * @endcode
   */
  protected $dev;

  /**
   * The current theme info.
   *
   * @var array
   */
  protected $info;

  /**
   * A URL for where a livereload instance is listening, if set.
   *
   * @var string
   *
   * This property can only be set via `settings.local.php`:
   *
   * @code
   * // Enable default value: //127.0.0.1:35729/livereload.js.
   * $settings['theme.livereload'] = TRUE;
   *
   * // Or, set just the port number: //127.0.0.1:12345/livereload.js.
   * $settings['theme.livereload'] = 12345;
   *
   * // Or, Set an explicit URL.
   * $settings['theme.livereload'] = '//127.0.0.1:35729/livereload.js';
   * @endcode
   */
  protected $livereload;

  /**
   * The theme machine name.
   *
   * @var string
   */
  protected $name;

  /**
   * An array of Setting instances.
   *
   * @var \Drupal\bootstrap\Plugin\Setting\SettingInterface[]
   */
  protected $settings;

  /**
   * The current theme Extension object.
   *
   * @var \Drupal\Core\Extension\Extension
   */
  protected $theme;

  /**
   * An array of installed themes.
   *
   * @var array
   */
  protected $themes;

  /**
   * Theme handler object.
   *
   * @var \Drupal\Core\Extension\ThemeHandlerInterface
   */
  protected $themeHandler;

  /**
   * The update plugin manager.
   *
   * @var \Drupal\bootstrap\Plugin\UpdateManager
   */
  protected $updateManager;

  /**
   * Theme constructor.
   *
   * @param \Drupal\Core\Extension\Extension $theme
   *   A theme \Drupal\Core\Extension\Extension object.
   * @param \Drupal\Core\Extension\ThemeHandlerInterface $theme_handler
   *   The theme handler object.
   */
  public function __construct(Extension $theme, ThemeHandlerInterface $theme_handler) {
    // Determine if "development mode" is set.
    $this->dev = !!Settings::get('theme.dev');

    // Determine the URL for livereload, if set.
    $this->livereload = '';
    if ($livereload = Settings::get('theme.livereload')) {
      // If TRUE, then set the port to the default used by grunt-contrib-watch.
      if ($livereload === TRUE) {
        $livereload = '//127.0.0.1:35729/livereload.js';
      }
      // If an integer, assume it's a port.
      elseif (is_int($livereload)) {
        $livereload = "//127.0.0.1:$livereload/livereload.js";
      }
      // If it's scalar, attempt to parse the URL.
      elseif (is_scalar($livereload)) {
        try {
          $livereload = Url::fromUri($livereload)->toString();
        }
        catch (\Exception $e) {
          $livereload = '';
        }
      }

      // Typecast livereload URL to a string.
      $this->livereload = "$livereload" ?: '';
    }

    $this->name = $theme->getName();
    $this->theme = $theme;
    $this->themeHandler = $theme_handler;
    $this->themes = $this->themeHandler->listInfo();
    $this->info = isset($this->themes[$this->name]->info) ? $this->themes[$this->name]->info : [];
    $this->bootstrap = $this->subthemeOf('bootstrap');

    // Only install the theme if it's Bootstrap based and there are no schemas
    // currently set.
    if ($this->isBootstrap() && !$this->getSetting('schemas')) {
      try {
        $this->install();
      }
      catch (\Exception $e) {
        // Intentionally left blank.
        // @see https://www.drupal.org/node/2697075
      }
    }
  }

  /**
   * Serialization method.
   */
  public function __sleep() {
    // Only store the theme name.
    return ['name'];
  }

  /**
   * Unserialize method.
   */
  public function __wakeup() {
    $theme_handler = Bootstrap::getThemeHandler();
    $theme = $theme_handler->getTheme($this->name);
    $this->__construct($theme, $theme_handler);
  }

  /**
   * Returns the theme machine name.
   *
   * @return string
   *   Theme machine name.
   */
  public function __toString() {
    return $this->getName();
  }

  /**
   * Retrieves the theme's settings array appropriate for drupalSettings.
   *
   * @return array
   *   The theme settings for drupalSettings.
   */
  public function drupalSettings() {
    // Immediately return if theme is not Bootstrap based.
    if (!$this->isBootstrap()) {
      return [];
    }

    $cache = $this->getCache('drupalSettings');
    $drupal_settings = $cache->getAll();
    if (!$drupal_settings) {
      foreach ($this->getSettingPlugin() as $name => $setting) {
        if ($setting->drupalSettings()) {
          $drupal_settings[$name] = TRUE;
        }
      }
      $cache->setMultiple($drupal_settings);
    }

    $drupal_settings = array_intersect_key($this->settings()->get(), $drupal_settings);

    // Indicate that theme is in dev mode.
    if ($this->isDev()) {
      $drupal_settings['dev'] = TRUE;
    }

    return $drupal_settings;
  }

  /**
   * Wrapper for the core file_scan_directory() function.
   *
   * Finds all files that match a given mask in the given directories and then
   * caches the results. A general site cache clear will force new scans to be
   * initiated for already cached directories.
   *
   * @param string $mask
   *   The preg_match() regular expression of the files to find.
   * @param string $subdir
   *   Sub-directory in the theme to start the scan, without trailing slash. If
   *   not set, the base path of the current theme will be used.
   * @param array $options
   *   Options to pass, see file_scan_directory() for addition options:
   *   - ignore_flags: (int|FALSE) A bitmask to indicate which directories (if
   *     any) should be skipped during the scan. Must also not contain a
   *     "nomask" property in $options. Value can be any of the following:
   *     - \Drupal\bootstrap::IGNORE_CORE
   *     - \Drupal\bootstrap::IGNORE_ASSETS
   *     - \Drupal\bootstrap::IGNORE_DOCS
   *     - \Drupal\bootstrap::IGNORE_DEV
   *     - \Drupal\bootstrap::IGNORE_THEME
   *     Pass FALSE to iterate over all directories in $dir.
   *
   * @return array
   *   An associative array (keyed on the chosen key) of objects with 'uri',
   *   'filename', and 'name' members corresponding to the matching files.
   *
   * @see file_scan_directory()
   */
  public function fileScan($mask, $subdir = NULL, array $options = []) {
    $path = $this->getPath();

    // Append addition sub-directories to the path if they were provided.
    if (isset($subdir)) {
      $path .= '/' . $subdir;
    }

    // Default ignore flags.
    $options += [
      'ignore_flags' => self::IGNORE_DEFAULT,
    ];
    $flags = $options['ignore_flags'];
    if ($flags === self::IGNORE_DEFAULT) {
      $flags = self::IGNORE_CORE | self::IGNORE_ASSETS | self::IGNORE_DOCS | self::IGNORE_DEV;
    }

    // Save effort by skipping directories that are flagged.
    if (!isset($options['nomask']) && $flags) {
      $ignore_directories = [];
      if ($flags & self::IGNORE_ASSETS) {
        $ignore_directories += ['assets', 'css', 'images', 'js'];
      }
      if ($flags & self::IGNORE_CORE) {
        $ignore_directories += ['config', 'lib', 'src'];
      }
      if ($flags & self::IGNORE_DOCS) {
        $ignore_directories += ['docs', 'documentation'];
      }
      if ($flags & self::IGNORE_DEV) {
        $ignore_directories += [
          'bower_components',
          'grunt',
          'node_modules',
          'starterkits',
        ];
      }
      if ($flags & self::IGNORE_TEMPLATES) {
        $ignore_directories += ['templates', 'theme'];
      }
      if (!empty($ignore_directories)) {
        $options['nomask'] = '/^' . implode('|', $ignore_directories) . '$/';
      }
    }

    // Retrieve cache.
    $cache = $this->getCache('files');

    // Generate a unique hash for all parameters passed as a change in any of
    // them could potentially return different results.
    $hash = Crypt::generateBase64HashIdentifier($options, [$mask, $path]);

    if (!$cache->has($hash)) {
      if ($fileSystem = \Drupal::service('file_system')) {
        $files = $fileSystem->scanDirectory($path, $mask, $options);
      }
      $cache->set($hash, $files);
    }
    return $cache->get($hash, []);
  }

  /**
   * Retrieves the full base/sub-theme ancestry of a theme.
   *
   * @param bool $reverse
   *   Whether or not to return the array of themes in reverse order, where the
   *   active theme is the first entry.
   *
   * @return \Drupal\bootstrap\Theme[]
   *   An associative array of \Drupal\bootstrap objects (theme), keyed
   *   by machine name.
   */
  public function getAncestry($reverse = FALSE) {
    $ancestry = $this->themeHandler->getBaseThemes($this->themes, $this->getName());
    foreach (array_keys($ancestry) as $name) {
      $ancestry[$name] = Bootstrap::getTheme($name, $this->themeHandler);
    }
    $ancestry[$this->getName()] = $this;
    return $reverse ? array_reverse($ancestry) : $ancestry;
  }

  /**
   * Retrieves an individual item from a theme's cache in the database.
   *
   * @param string $name
   *   The name of the item to retrieve from the theme cache.
   * @param array $context
   *   Optional. An array of additional context to use for retrieving the
   *   cached storage.
   * @param mixed $default
   *   Optional. The default value to use if $name does not exist.
   *
   * @return mixed|\Drupal\bootstrap\Utility\StorageItem
   *   The cached value for $name.
   */
  public function getCache($name, array $context = [], $default = []) {
    static $cache = [];

    // Prepend the theme name as the first context item, followed by cache name.
    array_unshift($context, $name);
    array_unshift($context, $this->getName());

    // Join context together with ":" and use it as the name.
    $name = implode(':', $context);

    if (!isset($cache[$name])) {
      $storage = self::getStorage();
      $value = $storage->get($name);
      if (!isset($value)) {
        $value = is_array($default) ? new StorageItem($default, $storage) : $default;
        $storage->set($name, $value);
      }
      $cache[$name] = $value;
    }

    return $cache[$name];
  }

  /**
   * Retrieves the set CDN Provider instance for the theme.
   *
   * @return \Drupal\bootstrap\Plugin\Provider\ProviderInterface
   *   A CDN Provider instance.
   */
  public function getCdnProvider() {
    $provider = $this->getSetting('cdn_provider');
    $providers = $this->getCdnProviders();
    return isset($providers[$provider]) ? $providers[$provider] : ProviderManager::broken();
  }

  /**
   * Retrieves all available CDN Provider instances.
   *
   * @return \Drupal\bootstrap\Plugin\Provider\ProviderInterface[]
   *   All CDN Provider instances.
   */
  public function getCdnProviders() {
    if (!isset($this->cdnProviders)) {
      $this->cdnProviders = [];

      // Only continue if the theme is Bootstrap based.
      if ($this->isBootstrap()) {
        $provider_manager = new ProviderManager($this);
        foreach ($provider_manager->getDefinitions() as $provider => $definition) {
          // Ignore hidden providers.
          if (!empty($definition['hidden'])) {
            continue;
          }
          $this->cdnProviders[$provider] = $provider_manager->get($provider, ['theme' => $this]);
        }
      }
    }
    return $this->cdnProviders;
  }

  /**
   * Retrieves the theme info.
   *
   * @param string $property
   *   A specific property entry from the theme's info array to return.
   *
   * @return array
   *   The entire theme info or a specific item if $property was passed.
   */
  public function getInfo($property = NULL) {
    if (isset($property)) {
      return isset($this->info[$property]) ? $this->info[$property] : NULL;
    }
    return $this->info;
  }

  /**
   * Returns the machine name of the theme.
   *
   * @return string
   *   The machine name of the theme.
   */
  public function getName() {
    return $this->theme->getName();
  }

  /**
   * Returns the relative path of the theme.
   *
   * @return string
   *   The relative path of the theme.
   */
  public function getPath() {
    return $this->theme->getPath();
  }

  /**
   * Retrieves pending updates for the theme.
   *
   * @return \Drupal\bootstrap\Plugin\Update\UpdateInterface[]
   *   An array of update plugin objects.
   */
  public function getPendingUpdates() {
    $pending = [];

    // Only continue if the theme is Bootstrap based.
    if ($this->isBootstrap()) {
      $current_theme = $this->getName();
      $schemas = $this->getSetting('schemas', []);
      foreach ($this->getAncestry() as $ancestor) {
        $ancestor_name = $ancestor->getName();
        if (!isset($schemas[$ancestor_name])) {
          $schemas[$ancestor_name] = \Drupal::CORE_MINIMUM_SCHEMA_VERSION;
          $this->setSetting('schemas', $schemas);
        }
        $pending_updates = $ancestor->getUpdateManager()->getPendingUpdates($current_theme === $ancestor_name);
        foreach ($pending_updates as $schema => $update) {
          if ((int) $schema > (int) $schemas[$ancestor_name]) {
            $pending[] = $update;
          }
        }
      }
    }

    return $pending;
  }

  /**
   * Retrieves a theme setting.
   *
   * @param string $name
   *   The name of the setting to be retrieved.
   * @param mixed $default
   *   A default value to provide if the setting is not found or if the plugin
   *   does not have a "defaultValue" annotation key/value pair. Typically,
   *   you will likely never need to use this unless in rare circumstances
   *   where the setting plugin exists but needs a default value not able to
   *   be set by conventional means (e.g. empty array).
   *
   * @return mixed
   *   The value of the requested setting, NULL if the setting does not exist
   *   and no $default value was provided.
   *
   * @see theme_get_setting()
   */
  public function getSetting($name, $default = NULL) {
    $value = $this->settings()->get($name);
    return !isset($value) ? $default : $value;
  }

  /**
   * Retrieves a theme's setting plugin instance(s).
   *
   * @param string $name
   *   Optional. The name of a specific setting plugin instance to return.
   * @param bool $rebuild
   *   Flag indicating whether to reset any cached definitions and rebuild
   *   the settings.
   *
   * @return \Drupal\bootstrap\Plugin\Setting\SettingInterface|\Drupal\bootstrap\Plugin\Setting\SettingInterface[]|null
   *   If $name was provided, it will either return a specific setting plugin
   *   instance or NULL if not set. If $name was omitted it will return an array
   *   of setting plugin instances, keyed by their name.
   */
  public function getSettingPlugin($name = NULL, $rebuild = FALSE) {
    if (!isset($this->settings) || $rebuild) {
      $settings = [];
      if ($this->isBootstrap()) {
        $setting_manager = new SettingManager($this);
        if ($rebuild) {
          $setting_manager->clearCachedDefinitions();
        }
        $plugin_ids = array_keys($setting_manager->getDefinitions());
        foreach ($plugin_ids as $plugin_id) {
          $settings[$plugin_id] = $setting_manager->createInstance($plugin_id);
        }
      }
      $this->settings = $settings;
    }

    // Return a specific setting plugin.
    if (isset($name)) {
      return isset($this->settings[$name]) ? $this->settings[$name] : NULL;
    }

    // Return all setting plugins.
    return $this->settings;
  }

  /**
   * Retrieves the theme's cache from the database.
   *
   * @return \Drupal\bootstrap\Utility\Storage
   *   The cache object.
   */
  public function getStorage() {
    static $cache = [];
    $theme = $this->getName();
    if (!isset($cache[$theme])) {
      $cache[$theme] = new Storage($theme);
    }
    return $cache[$theme];
  }

  /**
   * Retrieves the human-readable title of the theme.
   *
   * @return string
   *   The theme title or machine name as a fallback.
   */
  public function getTitle() {
    return $this->getInfo('name') ?: $this->getName();
  }

  /**
   * Retrieves the update plugin manager for the theme.
   *
   * @return \Drupal\bootstrap\Plugin\UpdateManager|false
   *   The Update plugin manager or FALSE if theme is not Bootstrap based.
   */
  public function getUpdateManager() {
    // Immediately return if theme is not Bootstrap based.
    if (!$this->isBootstrap()) {
      return FALSE;
    }

    if (!$this->updateManager) {
      $this->updateManager = new UpdateManager($this);
    }
    return $this->updateManager;
  }

  /**
   * Determines whether or not if the theme has Bootstrap Framework Glyphicons.
   */
  public function hasGlyphicons() {
    $glyphicons = $this->getCache('glyphicons');
    if (!$glyphicons->has($this->getName())) {
      $exists = FALSE;
      foreach ($this->getAncestry(TRUE) as $ancestor) {
        if ($ancestor->getSetting('cdn_provider') || $ancestor->fileScan('/glyphicons-halflings-regular\.(eot|svg|ttf|woff)$/', NULL, ['ignore_flags' => FALSE])) {
          $exists = TRUE;
          break;
        }
      }
      $glyphicons->set($this->getName(), $exists);
    }
    return $glyphicons->get($this->getName(), FALSE);
  }

  /**
   * Includes a file from the theme.
   *
   * @param string $file
   *   The file name, including the extension.
   * @param string $path
   *   The path to the file in the theme. Defaults to: "includes". Set to FALSE
   *   or and empty string if the file resides in the theme's root directory.
   *
   * @return bool
   *   TRUE if the file exists and is included successfully, FALSE otherwise.
   */
  public function includeOnce($file, $path = 'includes') {
    static $includes = [];
    $file = preg_replace('`^/?' . $this->getPath() . '/?`', '', $file);
    $file = strpos($file, '/') !== 0 ? $file = "/$file" : $file;
    $path = is_string($path) && !empty($path) && strpos($path, '/') !== 0 ? $path = "/$path" : '';
    $include = DRUPAL_ROOT . '/' . $this->getPath() . $path . $file;
    if (!isset($includes[$include])) {
      $includes[$include] = !!@include_once $include;
      if (!$includes[$include]) {
        \Drupal::messenger()->addMessage(t('Could not include file: @include', ['@include' => $include]), 'error');
      }
    }
    return $includes[$include];
  }

  /**
   * Installs a Bootstrap based theme.
   */
  protected function install() {
    // Immediately return if theme is not Bootstrap based.
    if (!$this->isBootstrap()) {
      return;
    }

    $schemas = [];
    foreach ($this->getAncestry() as $ancestor) {
      $schemas[$ancestor->getName()] = $ancestor->getUpdateManager()->getLatestSchema();
    }
    $this->setSetting('schemas', $schemas);
  }

  /**
   * Indicates whether the theme is bootstrap based.
   *
   * @return bool
   *   TRUE or FALSE
   */
  public function isBootstrap() {
    return $this->bootstrap;
  }

  /**
   * Indicates whether the theme is in "development mode".
   *
   * @return bool
   *   TRUE or FALSE
   *
   * @see \Drupal\bootstrap\Theme::dev
   */
  public function isDev() {
    return $this->dev;
  }

  /**
   * Returns the livereload URL set, if any.
   *
   * @see \Drupal\bootstrap\Theme::livereload
   *
   * @return string
   *   The livereload URL.
   */
  public function livereloadUrl() {
    return $this->livereload;
  }

  /**
   * Removes a theme setting.
   *
   * @param string $name
   *   Name of the theme setting to remove.
   */
  public function removeSetting($name) {
    $this->settings()->clear($name)->save();
  }

  /**
   * Sets a value for a theme setting.
   *
   * @param string $name
   *   Name of the theme setting.
   * @param mixed $value
   *   Value to associate with the theme setting.
   */
  public function setSetting($name, $value) {
    $this->settings()->set($name, $value)->save();
  }

  /**
   * Retrieves the theme settings instance.
   *
   * @return \Drupal\bootstrap\ThemeSettings
   *   All settings.
   */
  public function settings() {
    static $themes = [];
    $name = $this->getName();
    if (!isset($themes[$name])) {
      $themes[$name] = new ThemeSettings($this);
    }
    return $themes[$name];
  }

  /**
   * Determines whether or not a theme is a sub-theme of another.
   *
   * @param string|\Drupal\bootstrap\Theme $theme
   *   The name or theme Extension object to check.
   *
   * @return bool
   *   TRUE or FALSE
   */
  public function subthemeOf($theme) {
    return (string) $theme === $this->getName() || in_array($theme, array_keys(self::getAncestry()));
  }

  /****************************************************************************
   * Deprecated methods.
   ***************************************************************************/

  /**
   * Retrieves the CDN Provider.
   *
   * @param string $provider
   *   Optional. A CDN Provider name. If not set, defaults to the CDN
   *   provider set in the theme settings.
   *
   * @return \Drupal\bootstrap\Plugin\Provider\ProviderInterface|false
   *   A provider instance or FALSE if no provider is set.
   *
   * @deprecated in 8.x-3.18, will be removed in a future release.
   *
   * @see \Drupal\bootstrap\Theme::getCdnProvider()
   * @see \Drupal\bootstrap\Plugin\ProviderManager::load()
   */
  public function getProvider($provider = NULL) {
    $provider = $provider ?: $this->getSetting('cdn_provider');
    $providers = $this->getProviders();
    if (!isset($providers[$provider])) {
      return FALSE;
    }
    return $providers[$provider];
  }

  /**
   * Retrieves all CDN Providers.
   *
   * @return \Drupal\bootstrap\Plugin\Provider\ProviderInterface[]
   *   All provider instances.
   *
   * @deprecated in 8.x-3.18, will be removed in a future release.
   *
   * @see \Drupal\bootstrap\Theme::getCdnProviders()
   */
  public function getProviders() {
    return !$this->isBootstrap() ? [] : $this->getCdnProviders();
  }

  /**
   * Retrieves the theme's setting plugin instances.
   *
   * @return \Drupal\bootstrap\Plugin\Setting\SettingInterface[]
   *   An associative array of setting objects, keyed by their name.
   *
   * @deprecated in 8.x-3.1, will be removed in a future release.
   *
   * @see \Drupal\bootstrap\Theme::getSettingPlugin()
   */
  public function getSettingPlugins() {
    Bootstrap::deprecated();
    return $this->getSettingPlugin();
  }

}
