<?php

namespace Drupal\bootstrap\Plugin\Provider;

use Drupal\bootstrap\Bootstrap;

/**
 * The "drupal_bootstrap_styles" CDN Provider plugin.
 *
 * @ingroup plugins_provider
 *
 * @BootstrapProvider(
 *   id = "drupal_bootstrap_styles",
 *   label = @Translation("Drupal Bootstrap Styles"),
 *   description = @Translation("Provides styles that bridge the gap between Drupal and Bootstrap."),
 *   hidden = true,
 * )
 */
class DrupalBootstrapStyles extends JsDelivr {

  const KNOWN_FALL_BACK_VERSION = '0.0.1';

  /**
   * Retrieves the latest version of the published NPM package.
   *
   * While this isn't technically necessary, jsDelivr have been known to not
   * favor "version-less" requests. This ensures that a specific and published
   * NPM version is always used.
   *
   * @return string
   *   The latest version.
   */
  protected function getLatestVersion() {
    $json = $this->request('https://data.jsdelivr.com/v1/package/npm/@unicorn-fail/drupal-bootstrap-styles', ['ttl' => static::TTL_ONE_WEEK])->getData();
    return isset($json['tags']['latest']) ? $json['tags']['latest'] : static::KNOWN_FALL_BACK_VERSION;
  }

  /**
   * {@inheritdoc}
   */
  protected function getApiAssetsUrlTemplate() {
    $latest = $this->getLatestVersion();
    return "https://cdn.jsdelivr.net/npm/@unicorn-fail/drupal-bootstrap-styles@$latest/dist/api.json";
  }

  /**
   * {@inheritdoc}
   */
  protected function getApiVersionsUrlTemplate() {
    $latest = $this->getLatestVersion();
    return "https://cdn.jsdelivr.net/npm/@unicorn-fail/drupal-bootstrap-styles@$latest/dist/api.json";
  }

  /**
   * {@inheritdoc}
   */
  protected function getCdnUrlTemplate() {
    $latest = $this->getLatestVersion();
    return "https://cdn.jsdelivr.net/npm/@unicorn-fail/drupal-bootstrap-styles@$latest/@file";
  }

  /**
   * {@inheritdoc}
   */
  protected function parseAssets(array $data, $library, $version, CdnAssets $assets = NULL) {
    if (!isset($assets)) {
      $assets = new CdnAssets();
    }

    $files = array_filter(isset($data['files']) ? $data['files'] : [], function ($file) use ($library, $version) {
      if (strpos($file['name'], '/dist/' . $version . '/' . Bootstrap::PROJECT_BRANCH . '/') !== 0) {
        return FALSE;
      }
      $theme = !!preg_match("`drupal-bootstrap-([\w]+)(\.min)?\.css$`", $file['name']);
      return ($library === 'bootstrap' && !$theme) || ($library === 'bootswatch' && $theme);
    });

    foreach ($files as $file) {
      $assets->append($this->getCdnUrl('drupal-bootstrap-styles', $version, !empty($file['symlink']) ? $file['symlink'] : $file['name'], $file));
    }

    return $assets;
  }

  /**
   * {@inheritdoc}
   */
  protected function parseVersions(array $data = []) {
    $versions = [];
    $files = isset($data['files']) ? $data['files'] : [];
    foreach ($files as $file) {
      if (preg_match("`^/?dist/(\d+\.\d+\.\d+)/(\d\.x-\d\.x)/drupal-bootstrap-?([\w]+)?(\.min)?\.css$`", $file['name'], $matches)) {
        $version = $matches[1];
        $branch = $matches[2];
        if ($branch === Bootstrap::PROJECT_BRANCH && $this->isValidVersion($version)) {
          $versions[$version] = $version;
        }
      }
    }
    return $versions;
  }

}
