<?php

/**
 * @file
 * Contains Drupal\url_embed\UrlEmbed.
 */

namespace Drupal\url_embed;

use Drupal\Core\Config\ConfigFactoryInterface;
use Embed\Embed;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Component\Datetime\TimeInterface;

/**
 * A service class for handling URL embeds.
 */
class UrlEmbed implements UrlEmbedInterface {
  use UrlEmbedHelperTrait;

  /**
   * Drupal\Core\Cache\CacheBackendInterface definition.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected $cacheBackend;

  /**
   * Drupal\Component\Datetime\TimeInterface
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * Drupal\Core\Config\ConfigFactoryInterface definition.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * @var array
   */
  public $config;

  /**
   * Constructs a UrlEmbed object.
   *
   * @param array $config
   *   (optional) The options passed to the adapter.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   (optional) The config factory.
   */
  public function __construct(CacheBackendInterface $cache_backend, TimeInterface $time, ConfigFactoryInterface $config_factory, array $config = []) {
    $this->cacheBackend = $cache_backend;
    $this->time = $time;
    $this->configFactory = $config_factory;
    $global_config = $config_factory->get('url_embed.settings');
    $defaults = [];

    if ($global_config->get('facebook_app_id') && $global_config->get('facebook_app_secret')) {
      $defaults['facebook']['key'] = $global_config->get('facebook_app_id') . '|' . $global_config->get('facebook_app_secret');
    }
    $this->config = array_replace_recursive($defaults, $config);
  }

  /**
   * @{inheritdoc}
   */
  public function getConfig() {
    return $this->config;
  }

  /**
   * @{inheritdoc}
   */
  public function setConfig(array $config) {
    $this->config = $config;
  }

  /**
   * @{inheritdoc}
   */
  public function getEmbed($request, array $config = []) {
    return Embed::create($request, array_replace_recursive($this->config, $config));
  }

  /**
   * @{inheritdoc}
   */
  public function getUrlInfo($url) {
    $data = [];
    $keys = [
      'aspectRatio',
      'code',
      'height',
      'providerName',
      'title',
      'type',
      'width',
    ];
    $cid = 'url_embed:' . $url;
    $expire = $this->configFactory->get('url_embed.settings')->get('cache_expiration');
    if ($expire != 0 && $cache = $this->cacheBackend->get($cid)) {
      $data = $cache->data;
    }
    else {
      if ($info = $this->urlEmbed()->getEmbed($url)) {
        foreach ($keys as $key) {
          $data[$key] = $info->{$key};
        }
        if ($expire != 0) {
          $expiration = ($expire == Cache::PERMANENT) ? Cache::PERMANENT : $this->time->getRequestTime() + $expire;
          $this->cacheBackend->set($cid, $data, $expiration);
        }
      }

    }

    return $data;
  }


}
