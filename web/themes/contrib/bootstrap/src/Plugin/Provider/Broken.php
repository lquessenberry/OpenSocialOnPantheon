<?php

namespace Drupal\bootstrap\Plugin\Provider;

/**
 * Broken CDN Provider instance.
 *
 * @ingroup plugins_provider
 *
 * @BootstrapProvider(
 *   id = "_broken",
 *   label = @Translation("Broken"),
 *   description = @Translation("Broken CDN Provider instance."),
 *   hidden = true,
 * )
 */
class Broken extends ProviderBase {

  /**
   * {@inheritdoc}
   */
  public function alterFrameworkLibrary(array &$framework, $min = NULL) {
    // Intentionally left empty.
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTtl($type) {
    return static::TTL_NEVER;
  }

  /**
   * {@inheritdoc}
   */
  public function getCdnAssets($version = NULL, $theme = NULL) {
    return new CdnAssets();
  }

  /**
   * {@inheritdoc}
   */
  public function getCdnExceptions($reset = TRUE) {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getCdnTheme() {
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getCdnThemes($version = NULL) {
    return new CdnAssets();
  }

  /**
   * {@inheritdoc}
   */
  public function getCdnVersion() {
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getCdnVersions() {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function resetCache() {
    // Intentionally left empty.
  }

  /**
   * {@inheritdoc}
   */
  public function supportsThemes() {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function supportsVersions() {
    return FALSE;
  }

  /****************************************************************************
   * Deprecated methods.
   ***************************************************************************/

  /**
   * {@inheritdoc}
   *
   * @deprecated in 8.x-3.18, will be removed in a future release.
   */
  public function processDefinition(array &$definition, $plugin_id) {
    // Intentionally left empty.
  }

  /**
   * {@inheritdoc}
   *
   * @deprecated in 8.x-3.18, will be removed in a future release.
   */
  public function processApi(array $json, array &$definition) {
    // Intentionally left empty.
  }

}
