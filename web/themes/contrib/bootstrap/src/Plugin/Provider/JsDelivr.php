<?php

namespace Drupal\bootstrap\Plugin\Provider;

/**
 * The "jsdelivr" CDN Provider plugin.
 *
 * @ingroup plugins_provider
 *
 * @BootstrapProvider(
 *   id = "jsdelivr",
 *   label = @Translation("jsDelivr"),
 *   description = @Translation("<a href=:jsdelivr target=_blank>jsDelivr</a> is a free multi-CDN infrastructure that uses <a href=:maxcdn target=_blank>MaxCDN</a>, <a href=:cloudflare target=_blank>Cloudflare</a> and many others to combine their powers for the good of the open source community... <a href=:read_more target=_blank>read more</a>", arguments = {
 *     ":jsdelivr" = "https://www.jsdelivr.com",
 *     ":maxcdn" = "https://www.maxcdn.com",
 *     ":cloudflare" = "https://www.cloudflare.com",
 *     ":read_more" = "https://www.jsdelivr.com/about",
 *   }),
 *   weight = -1
 * )
 */
class JsDelivr extends ApiProviderBase {

  /**
   * {@inheritdoc}
   */
  protected function getApiAssetsUrlTemplate() {
    return 'https://data.jsdelivr.com/v1/package/npm/@library@@version/flat';
  }

  /**
   * {@inheritdoc}
   */
  protected function getApiVersionsUrlTemplate() {
    return 'https://data.jsdelivr.com/v1/package/npm/@library';
  }

  /**
   * {@inheritdoc}
   */
  protected function getCdnUrlTemplate() {
    return 'https://cdn.jsdelivr.net/npm/@library@@version/@file';
  }

}
