<?php

namespace Drupal\bootstrap\Plugin\Provider;

use Drupal\bootstrap\Bootstrap;
use Drupal\Component\Render\FormattableMarkup;
use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Render\Markup;

/**
 * CDN Provider base that uses an API to populate its assets.
 *
 * @ingroup plugins_provider
 */
abstract class ApiProviderBase extends ProviderBase {

  /**
   * {@inheritdoc}
   */
  protected function discoverCdnAssets($version, $theme = NULL) {
    if ($this->supportsThemes()) {
      $themes = $this->getCdnThemes($version);
      if (isset($themes[$theme])) {
        return $themes[$theme];
      }
      // Fall back to the first available theme if possible (likely Bootstrap).
      return reset($themes) ?: new CdnAssets();
    }
    return $this->requestApiAssets('bootstrap', $version)->getTheme('bootstrap');
  }

  /**
   * {@inheritdoc}
   */
  protected function discoverCdnThemes($version) {
    $assets = new CdnAssets();
    foreach (['bootstrap', 'bootswatch'] as $library) {
      $assets = $this->requestApiAssets($library, $version, $assets);
    }
    return $assets->getThemes();
  }

  /**
   * {@inheritdoc}
   */
  protected function discoverCdnVersions() {
    return $this->requestApiVersions('bootstrap');
  }

  /**
   * Retrieves the URL to use for determining available versions from the API.
   *
   * @param string $library
   *   The library to request.
   * @param string $version
   *   The version to request.
   *
   * @return string
   *   The API URL to use.
   */
  protected function getApiAssetsUrl($library, $version) {
    return (string) new FormattableMarkup($this->getApiAssetsUrlTemplate(), [
      '@library' => Markup::create($this->mapLibrary($library)),
      '@version' => Markup::create($this->mapVersion($version, $library)),
    ]);
  }

  /**
   * Retrieves the API URL template to use when requesting a specific asset.
   *
   * Available placeholders (must be prepended with an at symbol, @):
   * - library - The library to request.
   * - version - The version to request.
   *
   * @return string
   *   The CDN URL template.
   */
  abstract protected function getApiAssetsUrlTemplate();

  /**
   * Retrieves the URL to use for determining available versions from the API.
   *
   * @param string $library
   *   The library to request.
   *
   * @return string
   *   The API URL to use.
   */
  protected function getApiVersionsUrl($library) {
    return (string) new FormattableMarkup($this->getApiVersionsUrlTemplate(), [
      '@library' => Markup::create($this->mapLibrary($library)),
    ]);
  }

  /**
   * Retrieves the API URL template to use for determining available versions.
   *
   * Available placeholders (must be prepended with an at symbol, @):
   * - library - The specific library being requested.
   *
   * @return string
   *   The CDN URL template.
   */
  abstract protected function getApiVersionsUrlTemplate();

  /**
   * Retrieves a CDN URL based on provided variables.
   *
   * @param string $library
   *   The library to request.
   * @param string $version
   *   The version to request.
   * @param string $file
   *   The file to request.
   * @param array $info
   *   Additional information about the file, if any.
   *
   * @return \Drupal\bootstrap\Plugin\Provider\CdnAsset
   *   A CDN Asset object, for a given URL.
   */
  protected function getCdnUrl($library, $version, $file, array $info = []) {
    $library = $this->mapLibrary($library);
    $version = $this->mapVersion($version, $library);

    // Check if the "file" is really a fully qualified URL.
    if (UrlHelper::isExternal($file)) {
      $url = $file;
    }
    // Otherwise, use the template.
    else {
      $url = (string) new FormattableMarkup($this->getCdnUrlTemplate(), [
        '@library' => Markup::create($library),
        '@version' => Markup::create($version),
        '@file' => Markup::create(ltrim($file, '/')),
      ]);
    }

    return new CdnAsset($url, $library, $version, $info);
  }

  /**
   * Retrieves the CDN URL template to use.
   *
   * Available placeholders (must be prepended with an at symbol, @):
   * - library - The library to request.
   * - version - The version to request.
   * - file - The file to request.
   * - theme - The theme to request.
   *
   * @return string
   *   The CDN URL template.
   */
  abstract protected function getCdnUrlTemplate();

  /**
   * Checks whether a version is valid.
   *
   * @param string $version
   *   The version to check.
   *
   * @return bool
   *   TRUE or FALSE
   *
   * @todo Move regular expression to a constant once PHP 5.5 is no longer
   *   supported.
   */
  public static function isValidVersion($version) {
    return !!is_string($version) && preg_match('/^' . substr(Bootstrap::FRAMEWORK_VERSION, 0, 1) . '\.\d+\.\d+$/', $version);
  }

  /**
   * Allows providers a way to map a library to a different library.
   *
   * @param string $library
   *   The library to map.
   *
   * @return string
   *   The mapped library.
   */
  protected function mapLibrary($library) {
    return $library;
  }

  /**
   * {@inheritdoc}
   */
  protected function mapVersion($version, $library = NULL) {
    $mapped = [];

    // While the Bootswatch project attempts to maintain version parity with
    // Bootstrap, it doesn't always happen. This causes issues when the system
    // expects a 1:1 version match between Bootstrap and Bootswatch.
    // @see https://github.com/thomaspark/bootswatch/issues/892#ref-issue-410070082
    if ($library === 'bootswatch') {
      // This version is "broken" because of jsDelivr's API limit.
      $mapped['3.4.1'] = '3.4.0';
      // This version doesn't exist.
      $mapped['3.1.1'] = '3.2.0';
    }

    return isset($mapped[$version]) ? $mapped[$version] : $version;
  }

  /**
   * Parses assets provided by the API data.
   *
   * @param array $data
   *   The data to parse.
   * @param string $library
   *   The base URL each one of the $files are relative to, this usually
   *   should also include the version path prefix as well.
   * @param string $version
   *   A specific version to use.
   * @param \Drupal\bootstrap\Plugin\Provider\CdnAssets $assets
   *   An existing CdnAssets object, if chaining multiple requests together.
   *
   * @return \Drupal\bootstrap\Plugin\Provider\CdnAssets
   *   A CdnAssets object containing the necessary assets.
   */
  protected function parseAssets(array $data, $library, $version, CdnAssets $assets = NULL) {
    if (!isset($assets)) {
      $assets = new CdnAssets();
    }

    $files = [];
    // Support APIs that have a dedicated "files" property.
    if (isset($data['files'])) {
      $files = $data['files'];
    }
    elseif (isset($data['assets'])) {
      foreach ($data['assets'] as $asset) {
        // Support APIs that clump all the assets together, regardless of their
        // versions. Skip assets that don't match this version.
        if (isset($asset['version']) && $asset['version'] !== $version) {
          continue;
        }
        // Found the necessary files for the specified version.
        if (!empty($asset['files'])) {
          $files = $asset['files'];
          break;
        }
      }
    }
    foreach ($files as $file) {
      // Support APIs that simply use simple strings as files.
      if (is_string($file) && CdnAsset::isFileValid($file)) {
        $assets->append($this->getCdnUrl($library, $version, $file));
      }
      // Support APIs that put each file into its own array (metadata).
      elseif (is_array($file)) {
        // Support APIs that clump all the files together, regardless of their
        // versions. Skip assets that don't match this version.
        if (isset($file['version']) && $file['version'] !== $version) {
          continue;
        }
        // Support multiple keys for the "file".
        foreach (['filename', 'name', 'url', 'uri', 'path'] as $key) {
          if (!empty($file[$key]) && CdnAsset::isFileValid($file[$key])) {
            $assets->append($this->getCdnUrl($library, $version, $file[$key], $file));
            break;
          }
        }
      }
    }

    return $assets;
  }

  /**
   * Parses available versions provided by the API data.
   *
   * @param array $data
   *   The data to parse.
   *
   * @return array
   *   An associative array of versions, keyed by version.
   */
  protected function parseVersions(array $data = []) {
    $versions = [];

    // Support APIs that have a dedicated "versions" property.
    if (!empty($data['versions'])) {
      foreach ($data['versions'] as $version) {
        // Only extract valid versions.
        if ($this->isValidVersion($version)) {
          $versions[$version] = $version;
        }
      }
    }
    // Support APIs that have the version nested under individual assets.
    elseif (!empty($data['assets'])) {
      foreach ($data['assets'] as $asset) {
        if (isset($asset['version']) && $this->isValidVersion($asset['version'])) {
          $versions[$asset['version']] = $asset['version'];
        }
      }
    }

    return $versions;
  }

  /**
   * Requests available assets from the CDN Provider API.
   *
   * @param string $library
   *   The library to request.
   * @param string $version
   *   The version to request.
   * @param \Drupal\bootstrap\Plugin\Provider\CdnAssets $assets
   *   An existing CdnAssets object, if chaining multiple requests together.
   *
   * @return \Drupal\bootstrap\Plugin\Provider\CdnAssets
   *   The CdnAssets provided by the API.
   */
  protected function requestApiAssets($library, $version, CdnAssets $assets = NULL) {
    $url = $this->getApiAssetsUrl($library, $version);
    $options = ['ttl' => $this->getCacheTtl(static::CACHE_ASSETS)];
    $data = $this->request($url, $options)->getData();

    // If bootstrap data could not be returned, provide defaults.
    if (!$data && $this->cdnExceptions && $library === 'bootstrap') {
      $data = [
        'files' => [
          '/dist/css/bootstrap.css',
          '/dist/js/bootstrap.js',
          '/dist/css/bootstrap.min.css',
          '/dist/js/bootstrap.min.js',
        ],
      ];
    }

    // Parse the files from data.
    return $this->parseAssets($data, $library, $version, $assets);
  }

  /**
   * Requests available versions from the CDN Provider API.
   *
   * @param string $library
   *   The library to request versions for.
   *
   * @return array
   *   An associative array of versions, keyed by version.
   */
  public function requestApiVersions($library) {
    $url = $this->getApiVersionsUrl($library);
    $options = ['ttl' => $this->getCacheTtl(static::CACHE_VERSIONS)];
    $data = $this->request($url, $options)->getData();

    // If bootstrap data could not be returned, provide defaults.
    if (!$data && $this->cdnExceptions && $library === 'bootstrap') {
      $data = ['versions' => [Bootstrap::FRAMEWORK_VERSION]];
    }

    return $this->parseVersions($data);
  }

  /**
   * {@inheritdoc}
   *
   * @deprecated in 8.x-3.18, will be removed in a future release.
   */
  public function processDefinition(array &$definition, $plugin_id) {
    // Intentionally left blank so it doesn't trigger a deprecation warning.
  }

}
