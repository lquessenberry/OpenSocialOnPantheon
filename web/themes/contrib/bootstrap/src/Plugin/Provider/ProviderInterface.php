<?php

namespace Drupal\bootstrap\Plugin\Provider;

use Drupal\Component\Plugin\DerivativeInspectionInterface;
use Drupal\Component\Plugin\PluginInspectionInterface;

/**
 * ProviderInterface.
 *
 * @ingroup plugins_provider
 */
interface ProviderInterface extends PluginInspectionInterface, DerivativeInspectionInterface {

  /**
   * Defines the "assets" cache type.
   *
   * @var string
   */
  const CACHE_ASSETS = 'assets';

  /**
   * Defines the "library" cache type.
   *
   * @var string
   */
  const CACHE_LIBRARY = 'library';

  /**
   * Defines the "themes" cache type.
   *
   * @var string
   */
  const CACHE_THEMES = 'themes';

  /**
   * Defines the "versions" cache type.
   *
   * @var string
   */
  const CACHE_VERSIONS = 'versions';

  /**
   * Defines the "forever" time-to-live (TTL) value.
   *
   * @var int
   */
  const TTL_FOREVER = -1;

  /**
   * Defines the "never" time-to-live (TTL) value.
   *
   * @var int
   */
  const TTL_NEVER = 0;

  /**
   * Defines the "one day" time-to-live (TTL) value.
   *
   * @var int
   */
  const TTL_ONE_DAY = 86400;

  /**
   * Defines the "one week" time-to-live (TTL) value.
   *
   * @var int
   */
  const TTL_ONE_WEEK = 604800;

  /**
   * Defines the "one month" time-to-live (TTL) value.
   *
   * @var int
   */
  const TTL_ONE_MONTH = 2630000;

  /**
   * Defines the "three months" time-to-live (TTL) value.
   *
   * @var int
   */
  const TTL_THREE_MONTHS = 7776000;

  /**
   * Defines the "six months" time-to-live (TTL) value.
   *
   * @var int
   */
  const TTL_SIX_MONTHS = 15780000;

  /**
   * Defines the "one year" time-to-live (TTL) value.
   *
   * @var int
   */
  const TTL_ONE_YEAR = 31536000;

  /**
   * Alters the framework library.
   *
   * @param array $framework
   *   The framework library, passed by reference.
   */
  public function alterFrameworkLibrary(array &$framework);

  /**
   * Retrieves the cache time-to-live (TTL) value.
   *
   * @param string $type
   *   The type of cache TTL value. Can be one of the following types:
   *   - \Drupal\bootstrap\Plugin\Provider\ProviderInterface::CACHE_ASSETS
   *   - \Drupal\bootstrap\Plugin\Provider\ProviderInterface::CACHE_LIBRARY
   *   - \Drupal\bootstrap\Plugin\Provider\ProviderInterface::CACHE_THEMES
   *   - \Drupal\bootstrap\Plugin\Provider\ProviderInterface::CACHE_VERSIONS
   *   If an invalid type was specified, the resulting TTL value will be 0.
   *
   * @return int
   *   The cache TTL value, in seconds.
   */
  public function getCacheTtl($type);

  /**
   * Retrieves the assets from the CDN, if any.
   *
   * @param string $version
   *   Optional. The version of assets to return. If not set, the setting
   *   stored in the active theme will be used.
   * @param string $theme
   *   Optional. A specific set of themed assets to return, if any. If not set,
   *   the setting stored in the active theme will be used.
   *
   * @return \Drupal\bootstrap\Plugin\Provider\CdnAssets
   *   A CdnAssets object.
   */
  public function getCdnAssets($version = NULL, $theme = NULL);

  /**
   * Retrieves any CDN ProviderException objects triggered during discovery.
   *
   * Note: this is primarily used as a way to communicate in the UI that
   * the discovery of the CDN Provider's assets failed.
   *
   * @param bool $reset
   *   Flag indicating whether to remove the Exceptions once they have been
   *   retrieved.
   *
   * @return \Drupal\bootstrap\Plugin\Provider\ProviderException[]
   *   An array of CDN ProviderException objects, if any.
   */
  public function getCdnExceptions($reset = TRUE);

  /**
   * Retrieves the currently set CDN Provider theme.
   *
   * @return string
   *   The currently set CDN Provider theme.
   */
  public function getCdnTheme();

  /**
   * Retrieves the themes supported by the CDN Provider.
   *
   * @param string $version
   *   Optional. A specific version of themes to retrieve. If not set, the
   *   currently set CDN version of the active theme will be used.
   *
   * @return \Drupal\bootstrap\Plugin\Provider\CdnAssets[]
   *   An associative array of CDN assets, similar to what is returned in
   *   \Drupal\bootstrap\Plugin\Provider\ProviderBase::getCdnAssets(), but
   *   keyed by individual theme names.
   */
  public function getCdnThemes($version = NULL);

  /**
   * Retrieves the currently set CDN Provider version.
   *
   * @return string
   *   The currently set CDN Provider version.
   */
  public function getCdnVersion();

  /**
   * Retrieves the versions supported by the CDN Provider.
   *
   * @return array|false
   *   An associative array of versions, also keyed by the version.
   */
  public function getCdnVersions();

  /**
   * Retrieves the provider description.
   *
   * @return string
   *   The provider description.
   */
  public function getDescription();

  /**
   * Retrieves the provider human-readable label.
   *
   * @return string
   *   The provider human-readable label.
   */
  public function getLabel();

  /**
   * Removes any cached data the CDN Provider may have.
   */
  public function resetCache();

  /**
   * Indicates whether the CDN Provider supports themes.
   *
   * @return bool
   *   TRUE or FALSE
   */
  public function supportsThemes();

  /**
   * Indicates whether the CDN Provider supports versions.
   *
   * @return bool
   *   TRUE or FALSE
   */
  public function supportsVersions();

  /**
   * Tracks any newly generated CDN exceptions generated during a callable.
   *
   * @param callable $callable
   *   The callback to execute.
   *
   * @return \Drupal\bootstrap\Plugin\Provider\ProviderException[]
   *   An array of newly generated ProviderException objects, if any.
   */
  public function trackCdnExceptions(callable $callable);

  /****************************************************************************
   * Deprecated methods.
   ***************************************************************************/

  /**
   * Retrieves the API URL if set.
   *
   * @return string
   *   The API URL.
   *
   * @deprecated in 8.x-3.18, will be removed in a future release. There is no
   *   replacement for this functionality.
   */
  public function getApi();

  /**
   * Retrieves Provider assets for the active provider, if any.
   *
   * @param string|array $types
   *   The type of asset to retrieve: "css" or "js", defaults to an array
   *   array containing both if not set.
   *
   * @return array
   *   If $type is a string or an array with only one (1) item in it, the
   *   assets are returned as an indexed array of files. Otherwise, an
   *   associative array is returned keyed by the type.
   *
   * @deprecated in 8.x-3.18, will be removed in a future release.
   *
   * @see \Drupal\bootstrap\Plugin\Provider\ProviderInterface::getCdnAssets()
   */
  public function getAssets($types = NULL);

  /**
   * Retrieves the themes supported by the CDN Provider.
   *
   * @return array
   *   An array of themes. If the CDN Provider does not support any it will
   *   just be an empty array.
   *
   * @deprecated in 8.x-3.18, will be removed in a future release.
   *
   * @see \Drupal\bootstrap\Plugin\Provider\ProviderInterface::getCdnThemes()
   */
  public function getThemes();

  /**
   * Retrieves the versions supported by the CDN Provider.
   *
   * @return array
   *   An array of versions. If the CDN Provider does not support any it will
   *   just be an empty array.
   *
   * @deprecated in 8.x-3.18, will be removed in a future release.
   *
   * @see \Drupal\bootstrap\Plugin\Provider\ProviderInterface::getCdnVersions()
   */
  public function getVersions();

  /**
   * Flag indicating that the API data parsing failed.
   *
   * @return bool
   *   TRUE or FALSE
   *
   * @deprecated in 8.x-3.18, will be removed in a future release. There is no
   *   1:1 replacement for this functionality.
   *
   * @see \Drupal\bootstrap\Plugin\Provider\ProviderInterface::getCdnExceptions()
   */
  public function hasError();

  /**
   * Flag indicating that the API data was manually imported.
   *
   * @return bool
   *   TRUE or FALSE
   *
   * @deprecated in 8.x-3.18, will be removed in a future release. There is no
   *   replacement for this functionality.
   */
  public function isImported();

  /**
   * Processes the provider plugin definition upon discovery.
   *
   * @param array $definition
   *   The provider plugin definition.
   * @param string $plugin_id
   *   The plugin identifier.
   *
   * @deprecated in 8.x-3.18, will be removed in a future release. There is no
   *   replacement for this functionality.
   */
  public function processDefinition(array &$definition, $plugin_id);

  /**
   * Processes the provider plugin definition upon discovery.
   *
   * @param array $json
   *   The JSON data retrieved from the API request.
   * @param array $definition
   *   The provider plugin definition.
   *
   * @deprecated in 8.x-3.18, will be removed in a future release. There is no
   *   replacement for this functionality.
   */
  public function processApi(array $json, array &$definition);

}
