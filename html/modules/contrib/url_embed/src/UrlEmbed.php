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
   * @{inheritdoc}
   */
  public function __construct(CacheBackendInterface $cache_backend, TimeInterface $time, ConfigFactoryInterface $config_factory, array $config = []) {
    $this->config = $config;
    $this->cacheBackend = $cache_backend;
    $this->time = $time;
    $this->configFactory = $config_factory;
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
    return Embed::create($request, $config ?: $this->config);
  }

  /**
   * @{inheritdoc}
   */
  public function getUrlCode($url) {
    $data = '';
    $cid = 'url_embed:' . $url;
    $expire = $this->configFactory->get('url_embed.settings')->get('cache_expiration');
    if ($expire != 0 && $cache = $this->cacheBackend->get($cid)) {
      $data = $cache->data;
    }
    else {
      if ($info = $this->urlEmbed()->getEmbed($url)) {
        $data = $info->getCode();
        if ($expire != 0) {
          $expiration = ($expire == Cache::PERMANENT) ? Cache::PERMANENT : $this->time->getRequestTime() + $expire;
          $this->cacheBackend->set($cid, $data, $expiration);
        }
      }

    }

    return $data;
  }


}
