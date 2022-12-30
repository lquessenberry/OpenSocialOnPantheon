<?php

namespace Drupal\bootstrap\Plugin\Provider;

use Drupal\bootstrap\Bootstrap;
use Drupal\Component\Utility\UrlHelper;
use Symfony\Component\HttpFoundation\Response;

/**
 * The "custom" CDN Provider plugin.
 *
 * @ingroup plugins_provider
 *
 * @BootstrapProvider(
 *   id = "custom",
 *   label = @Translation("Custom"),
 *   description = @Translation("Allows the use of any CDN by providing the ability to manually specify a repository of available URLs."),
 *   weight = 100
 * )
 */
class Custom extends ProviderBase {

  /**
   * A list of valid Custom CDN URLs.
   *
   * @var string[]
   */
  protected $urls;

  /**
   * {@inheritdoc}
   */
  protected function discoverCdnAssets($version, $theme = NULL) {
    $themes = $this->getCdnThemes($version);
    return isset($themes[$theme]) ? $themes[$theme] : new CdnAssets();
  }

  /**
   * {@inheritdoc}
   */
  protected function discoverCdnThemes($version) {
    return $this->parseAssets($this->getUrls())->getThemes();
  }

  /**
   * {@inheritdoc}
   */
  protected function discoverCdnVersions() {
    $assets = $this->parseAssets($this->getUrls());
    $versions = [];
    foreach ($assets->toArray() as $asset) {
      if ($version = $asset->getVersion()) {
        $versions[$version] = $version;
      }
    }
    return $versions;
  }

  /**
   * {@inheritdoc}
   */
  protected function getCdnAssetsCacheData($version = NULL, $theme = NULL) {
    return parent::getCdnAssetsCacheData($version, $theme) + ['urls' => $this->getUrls()];
  }

  /**
   * {@inheritdoc}
   */
  protected function getCdnThemesCacheData($version = NULL) {
    return parent::getCdnThemesCacheData($version) + ['urls' => $this->getUrls()];
  }

  /**
   * {@inheritdoc}
   */
  protected function getCdnVersionsCacheData() {
    return parent::getCdnVersionsCacheData() + ['urls' => $this->getUrls()];
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTtl($type) {
    // Because these are static URLs provided by the user, they should just be
    // cached forever.
    return static::TTL_FOREVER;
  }

  /**
   * Retrieves an array of URLs that should be used in the Custom CDN.
   *
   * @return array
   *   An array of URLs.
   */
  protected function getUrls() {
    if (!isset($this->urls)) {
      $urls = [];
      $filtered = array_filter(explode("\n", $this->theme->getSetting('cdn_custom')));
      foreach ($filtered as $url) {
        try {
          $urls[] = $this->validateUrl($url);
        }
        catch (\Exception $e) {
          // Intentionally do nothing.
        }
      }
      $this->urls = $urls;
    }
    return $this->urls;
  }

  /**
   * Validates a URL.
   *
   * @param string $url
   *   The URL to validate.
   *
   * @return string
   *   The passed $url.
   */
  public function validateUrl($url) {
    if (!UrlHelper::isValid($url, TRUE)) {
      throw new InvalidCdnUrlException(sprintf('Malformed: %s', $url));
    }
    $response = Bootstrap::checkUrlIsReachable($url, ['method' => 'option']);
    if (($statusCode = $response->getStatusCode()) >= 400) {
      throw new InvalidCdnUrlException(sprintf('(%d) %s: %s', $statusCode, Response::$statusTexts[$statusCode], $url), $statusCode);
    }
    if (!$response->validMimeExtension()) {
      throw new InvalidCdnUrlException(sprintf('(%d) Mismatched MIME Type: %s [%s]', $statusCode, $url, $response->getMimeType()), $statusCode);
    }
    return $url;
  }

  /**
   * Parses URLs and places them in an "assets" like array.
   *
   * @param string[] $urls
   *   An array of URLs to process.
   *
   * @return \Drupal\bootstrap\Plugin\Provider\CdnAssets
   *   A CdnAssets object.
   */
  protected function parseAssets(array $urls) {
    $assets = new CdnAssets();
    foreach ($urls as $url) {
      // Skip invalid assets.
      if (!CdnAsset::isFileValid($url)) {
        continue;
      }
      $assets->append(new CdnAsset($url));
    }
    return $assets;
  }

  /**
   * {@inheritdoc}
   */
  public function processDefinition(array &$definition, $plugin_id) {
    // Intentionally left blank so it doesn't trigger a deprecation warning.
  }

}
