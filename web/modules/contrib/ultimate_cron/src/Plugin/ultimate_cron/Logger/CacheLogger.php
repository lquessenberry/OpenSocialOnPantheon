<?php

namespace Drupal\ultimate_cron\Plugin\ultimate_cron\Logger;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\ultimate_cron\Logger\LogEntry;
use Drupal\ultimate_cron\Logger\LoggerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Cache Logger.
 *
 * @LoggerPlugin(
 *   id = "cache",
 *   title = @Translation("Cache"),
 *   description = @Translation("Stores the last log entry (and only the last) in the cache."),
 * )
 */
class CacheLogger extends LoggerBase implements ContainerFactoryPluginInterface {

  /**
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected $cache;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, CacheBackendInterface $cache) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->cache = $cache;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition)  {
    $bin = isset($configuration['bin']) ? $configuration['bin'] : 'ultimate_cron_logger';
    return new static ($configuration, $plugin_id, $plugin_definition, $container->get('cache.' . $bin));
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return array(
      'bin' => 'ultimate_cron_logger',
      'timeout' => Cache::PERMANENT,
    );
  }

  /**
   * {@inheritdoc}
   */
  public function load($name, $lock_id = NULL, array $log_types = [ULTIMATE_CRON_LOG_TYPE_NORMAL]) {
    $log_entry = new LogEntry($name, $this);
    if (!$lock_id) {
      $cache =  $this->cache->get('uc-name:' . $name, TRUE);
      if (empty($cache) || empty($cache->data)) {
        return $log_entry;
      }
      $lock_id = $cache->data;
    }
    $cache = $this->cache->get('uc-lid:' . $lock_id, TRUE);

    if (!empty($cache->data)) {
      $log_entry->setData((array) $cache->data);
      $log_entry->finished = TRUE;
    }
    return $log_entry;
  }

  /**
   * {@inheritdoc}
   */
  public function getLogEntries($name, array $log_types, $limit = 10) {
    $log_entry = $this->load($name);
    return $log_entry->lid ? array($log_entry) : array();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form['bin'] = array(
      '#type' => 'textfield',
      '#title' => t('Cache bin'),
      '#description' => t('Select which cache bin to use for storing logs.'),
      '#default_value' => $this->configuration['bin'],
      '#fallback' => TRUE,
      '#required' => TRUE,
    );

    $form['timeout'] = array(
      '#type' => 'textfield',
      '#title' => t('Cache timeout'),
      '#description' => t('Seconds before cache entry expires (0 = never, -1 = on next general cache wipe).'),
      '#default_value' => $this->configuration['timeout'],
      '#fallback' => TRUE,
      '#required' => TRUE,
    );

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function save(LogEntry $log_entry) {
    if (!$log_entry->lid) {
      return;
    }

    $settings = $this->getConfiguration();

    $expire = $settings['timeout'] != Cache::PERMANENT ? \Drupal::time()->getRequestTime() + $settings['timeout'] : $settings['timeout'];

    $this->cache->set('uc-name:' . $log_entry->name, $log_entry->lid, $expire);
    $this->cache->set('uc-lid:' . $log_entry->lid, $log_entry->getData(), $expire);
  }

}
