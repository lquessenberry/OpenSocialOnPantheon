<?php

namespace Drupal\bootstrap\Plugin\Provider;

use Drupal\bootstrap\Bootstrap;
use Drupal\bootstrap\Plugin\PluginBase;
use Drupal\bootstrap\Plugin\ProviderManager;
use Drupal\bootstrap\Utility\Crypt;
use Drupal\bootstrap\Utility\Unicode;
use Drupal\Component\Serialization\Json;
use Drupal\Component\Utility\NestedArray;

/**
 * CDN Provider base class.
 *
 * @ingroup plugins_provider
 */
class ProviderBase extends PluginBase implements ProviderInterface {

  /**
   * The currently set assets.
   *
   * @var array
   *
   * @deprecated in 8.x-3.18, will be removed in a future release.
   */
  protected $assets = [];

  /**
   * The cache backend used for storing various permanent CDN Provider data.
   *
   * @var \Drupal\Core\KeyValueStore\KeyValueStoreInterface
   */
  protected $keyValue;

  /**
   * The cache backend used for storing various expirable CDN Provider data.
   *
   * @var \Drupal\Core\KeyValueStore\KeyValueStoreExpirableInterface
   */
  protected $keyValueExpirable;

  /**
   * The cache TTL values, in seconds, keyed by type.
   *
   * @var int[]
   *
   * @see \Drupal\bootstrap\Plugin\Provider\ProviderInterface
   */
  protected $cacheTtl = [];

  /**
   * The currently set CDN assets, keyed by a hash identifier.
   *
   * @var \Drupal\bootstrap\Plugin\Provider\CdnAssets[]
   */
  protected $cdnAssets;

  /**
   * A list of currently set Exception objects.
   *
   * @var \Drupal\bootstrap\Plugin\Provider\ProviderException[]
   */
  protected $cdnExceptions = [];

  /**
   * The versions supplied by the CDN Provider.
   *
   * @var array
   */
  protected $versions;

  /**
   * The themes supplied by the CDN Provider, keyed by version.
   *
   * @var array[]
   */
  protected $themes = [];

  /**
   * Adds a new CDN Provider exception.
   *
   * @param \Throwable $exception
   *   The exception message.
   */
  protected function addCdnException(\Throwable $exception) {
    $this->cdnExceptions[] = new ProviderException($this, $exception->getMessage(), $exception->getCode(), $exception);
  }

  /**
   * {@inheritdoc}
   *
   * @see \Drupal\bootstrap\Plugin\Provider\ProviderBase::getCdnAssetsCacheData()
   */
  public function alterFrameworkLibrary(array &$framework) {
    // Attempt to retrieve cached CDN assets from the database. This is
    // primarily used to avoid unnecessary API requests and speed up the
    // process during a cache rebuild. The "keyvalue.expirable" service is
    // used as it persists through cache rebuilds. In order to prevent stale
    // data, a hash is used constructed of various data relating to the CDN.
    // The cache is rebuilt if and when it has expired.
    // @see https://www.drupal.org/project/bootstrap/issues/3031415
    $data = $this->getCdnAssetsCacheData();
    $hash = Crypt::generateBase64HashIdentifier($data);

    // Retrieve the cached value or build it if necessary.
    $framework = $this->cacheGet('library', $hash, [], function () use ($framework, $data) {
      $version = isset($data['version']) ? $data['version'] : NULL;
      $theme = isset($data['theme']) ? $data['theme'] : NULL;
      $assets = $this->getCdnAssets($version, $theme)->toLibraryArray($data['min']);

      // Immediately return if there are no theme CDN assets to use.
      if (empty($assets)) {
        return $framework;
      }

      // Override the framework version with the CDN version that is being used.
      if (isset($data['version'])) {
        $framework['version'] = $data['version'];
      }

      // @todo Provide a UI setting for this?
      $styles = [];
      if ($this->theme->getSetting('cdn_styles', TRUE)) {
        $stylesProvider = ProviderManager::load($this->theme, 'drupal_bootstrap_styles');
        $styles = $stylesProvider->getCdnAssets($version, $theme)->toLibraryArray($data['min']);
      }

      // Merge the assets with the existing library info and return it.
      return NestedArray::mergeDeepArray([$assets, $styles, $framework], TRUE);
    });
  }

  /**
   * Retrieves a value from the CDN Provider cache.
   *
   * @param string $type
   *   The type of cache item to retrieve.
   * @param string $key
   *   Optional. A specific key of the item to retrieve. Note: this can be in
   *   the form of dot notation if the value is nested in an array. If not
   *   provided, the entire contents of $name will be returned.
   * @param mixed $default
   *   Optional. The default value to return if $key is not set.
   * @param callable $builder
   *   Optional. If provided, a builder will be invoked when there is no cache
   *   currently set. The return value of the build will be used to set the
   *   cached value, provided there are no CDN Provider exceptions generated.
   *   If there are, but you still need the cache to be set, reset them prior
   *   to returning from the builder callback.
   *
   * @return mixed
   *   The cached value if it's set or the value supplied to $default if not.
   */
  protected function cacheGet($type, $key = NULL, $default = NULL, callable $builder = NULL) {
    $ttl = $this->getCacheTtl($type);
    $never = $ttl === static::TTL_NEVER;
    $forever = $ttl === static::TTL_FOREVER;
    $cache = $forever ? $this->getKeyValue() : $this->getKeyValueExpirable();

    $data = $cache->get($type, []);

    if (!isset($key)) {
      return $data;
    }

    $parts = Unicode::splitDelimiter($key);
    $value = NestedArray::getValue($data, $parts, $key_exists);

    // Build the cache.
    if (!$key_exists && $builder) {
      $value = $builder($default);
      if (!isset($value)) {
        $value = $default;
      }
      NestedArray::setValue($data, $parts, $value);

      // Only set the cache if no CDN Provider exceptions were thrown.
      if (!$this->cdnExceptions && !$never) {
        if ($forever) {
          $cache->set($type, $data);
        }
        else {
          $cache->setWithExpire($type, $data, $ttl);
        }
      }

      return $value;
    }

    return $key_exists ? $value : $default;
  }

  /**
   * Discovers the assets supported by the CDN Provider.
   *
   * CDN Providers should sub-class this method to make requests and/or process
   * any necessary data.
   *
   * @param string $version
   *   The version of assets to return.
   * @param string $theme
   *   A specific set of themed assets to return, if any.
   *
   * @return \Drupal\bootstrap\Plugin\Provider\CdnAssets
   *   A CdnAssets object.
   */
  protected function discoverCdnAssets($version, $theme = NULL) {
    $assets = [];

    // Convert the deprecated array structure into a proper CdnAssets object.
    $data = $this->getAssets();
    foreach (['css', 'js'] as $type) {
      if (isset($data[$type])) {
        foreach ($data[$type] as $file) {
          $assets[] = new CdnAsset($file, NULL, $version);
        }
      }
      if (isset($data['min'][$type])) {
        foreach ($data['min'][$type] as $file) {
          $assets[] = new CdnAsset($file, NULL, $version);
        }
      }
    }

    return new CdnAssets($assets);
  }

  /**
   * Discovers the themes supported by the CDN Provider.
   *
   * CDN Providers should sub-class this method to make requests and/or process
   * any necessary data.
   *
   * @param string $version
   *   A specific version of themes to retrieve.
   *
   * @return array|false
   *   An associative array of theme data, similar to what is returned in
   *   \Drupal\bootstrap\Plugin\Provider\ProviderBase::discoverCdnAssets(), but
   *   keyed by the theme name.
   */
  protected function discoverCdnThemes($version) {
    return [];
  }

  /**
   * Discovers the versions supported by the CDN Provider.
   *
   * CDN Providers should sub-class this method to make requests and/or process
   * any necessary data.
   *
   * @return array|false
   *   An associative array of versions, also keyed by the version.
   */
  protected function discoverCdnVersions() {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTtl($type) {
    if (!isset($this->cacheTtl[$type])) {
      $this->cacheTtl[$type] = (int) $this->theme->getSetting("cdn_cache_ttl_$type", static::TTL_NEVER);
      // If TTL is -1, the set a far reaching date from now.
      if ($this->cacheTtl[$type] === static::TTL_FOREVER) {
        $this->cacheTtl[$type] = static::TTL_ONE_YEAR * 10;
      }
    }
    return $this->cacheTtl[$type];
  }

  /**
   * Retrieves the unique cache identifier for the CDN Provider.
   *
   * @return string
   *   The CDN Provider cache identifier.
   */
  protected function getCacheId() {
    return "theme:{$this->theme->getName()}:cdn:{$this->getPluginId()}";
  }

  /**
   * {@inheritdoc}
   */
  public function getCdnAssets($version = NULL, $theme = NULL) {
    if (!isset($this->cdnAssets)) {
      $this->cdnAssets = $this->cacheGet('assets');
    }

    $data = $this->getCdnAssetsCacheData($version, $theme);
    $hash = Crypt::generateBase64HashIdentifier($data);
    if (!isset($this->cdnAssets[$hash])) {
      $this->cdnAssets[$hash] = $this->cacheGet('assets', $hash, [], function () use ($data) {
        return $this->discoverCdnAssets($data['version'], $data['theme']);
      });
    }

    return $this->cdnAssets[$hash];
  }

  /**
   * Retrieves the data used to create a hash for CDN Assets.
   *
   * @param string $version
   *   Optional. A specific version to use.
   * @param string $theme
   *   Optional. A specific theme to use.
   *
   * @return array
   *   An array of components that will be serialized and hashed.
   *
   * @see \Drupal\bootstrap\Plugin\Provider\ProviderBase::getCdnAssets()
   * @see \Drupal\bootstrap\Plugin\Provider\ProviderBase::alterFrameworkLibrary()
   */
  protected function getCdnAssetsCacheData($version = NULL, $theme = NULL) {
    if (!isset($version) && $this->supportsVersions()) {
      $version = $this->getCdnVersion();
    }
    if (!isset($theme) && $this->supportsThemes()) {
      $theme = $this->getCdnTheme();
    }
    return [
      'ttl' => $this->getCacheTtl(static::CACHE_LIBRARY),
      'min' => [
        'css' => !!\Drupal::config('system.performance')->get('css.preprocess'),
        'js' => !!\Drupal::config('system.performance')->get('js.preprocess'),
      ],
      'provider' => $this->pluginId,
      'version' => $version,
      'theme' => $theme,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getCdnExceptions($reset = TRUE) {
    $exceptions = $this->cdnExceptions;
    if ($reset) {
      $this->cdnExceptions = [];
    }
    return $exceptions;
  }

  /**
   * {@inheritdoc}
   */
  public function getCdnTheme() {
    return $this->supportsThemes() ? $this->theme->getSetting('cdn_theme', 'bootstrap') : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getCdnThemes($version = NULL) {
    // Immediately return if the CDN Provider does not support themes.
    if (!$this->supportsThemes()) {
      return [];
    }

    $data = $this->getCdnThemesCacheData($version);
    $hash = Crypt::generateBase64HashIdentifier($data);
    if (!isset($this->themes[$hash])) {
      $this->themes[$hash] = $this->cacheGet('themes', $hash, [], function () use ($data) {
        return $this->discoverCdnThemes($data['version']);
      });
    }

    return $this->themes[$hash];
  }

  /**
   * Retrieves the data used to create a hash for CDN Themes.
   *
   * @param string $version
   *   Optional. A specific version to use. If not set, the
   *   currently set CDN version of the active theme will be used.
   *
   * @return array
   *   An array of components that will be serialized and hashed.
   *
   * @see \Drupal\bootstrap\Plugin\Provider\ProviderBase::getCdnThemes()
   */
  protected function getCdnThemesCacheData($version = NULL) {
    if (!isset($version) && $this->supportsVersions()) {
      $version = $this->getCdnVersion();
    }
    return [
      'ttl' => $this->getCacheTtl(static::CACHE_THEMES),
      'provider' => $this->pluginId,
      'version' => $version,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getCdnVersion() {
    return $this->supportsVersions() ? $this->theme->getSetting('cdn_version', Bootstrap::FRAMEWORK_VERSION) : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getCdnVersions() {
    // Immediately return if the CDN Provider does not support versions.
    if (!$this->supportsVersions()) {
      return [];
    }

    if (!isset($this->versions)) {
      $hash = Crypt::generateBase64HashIdentifier($this->getCdnVersionsCacheData());
      $this->versions = $this->cacheGet('versions', $hash, [], function () {
        return $this->discoverCdnVersions();
      });
    }
    return $this->versions;
  }

  /**
   * Retrieves the data used to create a hash for CDN Versions.
   *
   * @return array
   *   An array of components that will be serialized and hashed.
   *
   * @see \Drupal\bootstrap\Plugin\Provider\ProviderBase::getCdnVersions()
   */
  protected function getCdnVersionsCacheData() {
    return [
      'ttl' => $this->getCacheTtl(static::CACHE_THEMES),
      'provider' => $this->pluginId,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return $this->pluginDefinition['description'];
  }

  /**
   * Retrieves a permanent key/value storage instance.
   *
   * @return \Drupal\Core\KeyValueStore\KeyValueStoreInterface
   *   A permanent key/value storage instance.
   */
  protected function getKeyValue() {
    if (!isset($this->keyValue)) {
      $this->keyValue = \Drupal::keyValue($this->getCacheId());
    }
    return $this->keyValue;
  }

  /**
   * Retrieves a expirable key/value storage instance.
   *
   * @return \Drupal\Core\KeyValueStore\KeyValueStoreExpirableInterface
   *   An expirable key/value storage instance.
   */
  protected function getKeyValueExpirable() {
    if (!isset($this->keyValueExpirable)) {
      $this->keyValueExpirable = \Drupal::keyValueExpirable($this->getCacheId());
    }
    return $this->keyValueExpirable;
  }

  /**
   * {@inheritdoc}
   */
  public function getLabel() {
    return $this->pluginDefinition['label'] ?: $this->getPluginId();
  }

  /**
   * {@inheritdoc}
   */
  public function getThemes() {
    return $this->pluginDefinition['themes'];
  }

  /**
   * {@inheritdoc}
   */
  public function getVersions() {
    return $this->pluginDefinition['versions'];
  }

  /**
   * Allows providers a way to map a version to a different version.
   *
   * @param string $version
   *   The version to map.
   *
   * @return string
   *   The mapped version.
   */
  protected function mapVersion($version) {
    return $version;
  }

  /**
   * Initiates an HTTP request.
   *
   * @param string $url
   *   The URL to retrieve.
   * @param array $options
   *   The options to pass to the HTTP client.
   *
   * @return \Drupal\bootstrap\SerializedResponse
   *   A SerializedResponse object.
   */
  protected function request($url, array $options = []) {
    $response = Bootstrap::request($url, $options, $exception);
    if ($exception) {
      $this->addCdnException($exception);
    }
    return $response;
  }

  /**
   * {@inheritdoc}
   */
  public function resetCache() {
    $this->getKeyValue()->deleteAll();
    $this->getKeyValueExpirable()->deleteAll();

    // Invalidate library info if this provider is the one currently used.
    if ($this->theme->getCdnProvider()->getPluginId() === $this->pluginId) {
      /** @var \Drupal\Core\Cache\CacheTagsInvalidatorInterface $invalidator */
      $invalidator = \Drupal::service('cache_tags.invalidator');
      $invalidator->invalidateTags(['library_info']);
    }
  }

  /**
   * Sets CDN Provider exceptions, replacing any existing exceptions.
   *
   * @param \Throwable[] $exceptions
   *   The Exceptions to set.
   *
   * @return static
   */
  protected function setCdnExceptions(array $exceptions) {
    $this->cdnExceptions = [];
    foreach ($exceptions as $exception) {
      $this->addCdnException($exception);
    }
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function supportsThemes() {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function supportsVersions() {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function trackCdnExceptions(callable $callable) {
    // Retrieve existing exceptions.
    $existing = $this->getCdnExceptions();

    // Execute the callable.
    $callable($this);

    // Retrieve any newly generated exceptions.
    $new = $this->getCdnExceptions();

    // Merge the existing and newly generated exceptions and set them.
    $this->setCdnExceptions(array_merge($existing, $new));

    // Return the newly generated exceptions.
    return $new;
  }

  /****************************************************************************
   * Deprecated methods.
   ***************************************************************************/

  /**
   * {@inheritdoc}
   *
   * @deprecated in 8.x-3.18, will be removed in a future release.
   */
  public function getApi() {
    Bootstrap::deprecated();
    return $this->pluginDefinition['api'];
  }

  /**
   * {@inheritdoc}
   *
   * @deprecated in 8.x-3.18, will be removed in a future release.
   */
  public function getAssets($types = NULL) {
    Bootstrap::deprecated();
    return $this->assets;
  }

  /**
   * {@inheritdoc}
   *
   * @deprecated in 8.x-3.18, will be removed in a future release.
   */
  public function hasError() {
    Bootstrap::deprecated();
    return $this->pluginDefinition['error'];
  }

  /**
   * {@inheritdoc}
   *
   * @deprecated in 8.x-3.18, will be removed in a future release.
   */
  public function isImported() {
    Bootstrap::deprecated();
    return $this->pluginDefinition['imported'];
  }

  /**
   * {@inheritdoc}
   *
   * @deprecated in 8.x-3.18, will be removed in a future release.
   */
  public function processDefinition(array &$definition, $plugin_id) {
    // Due to code recursion and the need to keep this code in place for BC
    // reasons, this deprecated message should only be logged and not shown.
    Bootstrap::deprecated(FALSE);

    // Process API data.
    if ($api = $this->getApi()) {
      $provider_path = ProviderManager::FILE_PATH;

      // FILE_CREATE_DIRECTORY = 1 | FILE_MODIFY_PERMISSIONS = 2.
      $options = 1 | 2;
      if ($fileSystem = Bootstrap::fileSystem('prepareDirectory')) {
        $fileSystem->prepareDirectory($provider_path, $options);
      }
      else {
        file_prepare_directory($provider_path, $options);
      }

      // Use manually imported API data, if it exists.
      if (file_exists("$provider_path/$plugin_id.json") && ($imported_data = file_get_contents("$provider_path/$plugin_id.json"))) {
        $definition['imported'] = TRUE;
        try {
          $json = Json::decode($imported_data);
        }
        catch (\Exception $e) {
          // Intentionally left blank.
        }
      }
      // Otherwise, attempt to request API data if the provider has specified
      // an "api" URL to use.
      else {
        $json = Bootstrap::request($api)->getData();
      }

      if (!isset($json)) {
        $json = [];
        $definition['error'] = TRUE;
      }

      $this->processApi($json, $definition);
    }
  }

  /**
   * {@inheritdoc}
   *
   * @deprecated in 8.x-3.18, will be removed in a future release.
   */
  public function processApi(array $json, array &$definition) {
    Bootstrap::deprecated();
  }

}
