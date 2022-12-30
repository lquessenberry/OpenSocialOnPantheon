<?php

namespace Drupal\bootstrap\Plugin\Provider;

/**
 * The "cdnjs" CDN Provider plugin.
 *
 * @ingroup plugins_provider
 *
 * @BootstrapProvider(
 *   id = "cdnjs",
 *   label = @Translation("CDNJS"),
 *   description = @Translation("CDNJS is one of the most famous free and public web front-end CDN services which is used by ~2,999,000 websites worldwide."),
 * )
 */
class CdnJs extends ApiProviderBase {

  /**
   * {@inheritdoc}
   */
  protected function getApiAssetsUrlTemplate() {
    return 'https://api.cdnjs.com/libraries/@library';
  }

  /**
   * {@inheritdoc}
   */
  protected function getApiVersionsUrlTemplate() {
    return 'https://api.cdnjs.com/libraries/@library';
  }

  /**
   * {@inheritdoc}
   */
  protected function getCdnUrlTemplate() {
    return 'https://cdnjs.cloudflare.com/ajax/libs/@library/@version/@file';
  }

  /**
   * {@inheritdoc}
   */
  protected function mapLibrary($library) {
    // The cdnjs uses the old library name and doesn't have an alias.
    if ($library === 'bootstrap') {
      return 'twitter-bootstrap';
    }
    return parent::mapLibrary($library);
  }

}
