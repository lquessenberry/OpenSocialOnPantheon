<?php

namespace Drupal\bootstrap\Plugin\Setting\Advanced\Cdn;

/**
 * Due to BC reasons, this class cannot be moved.
 *
 * @todo Move namespace up one.
 */

use Drupal\bootstrap\Plugin\Provider\ProviderInterface;
use Drupal\bootstrap\Utility\Element;
use Drupal\Core\Form\FormStateInterface;

/**
 * Base class for "cdn_cache_ttl_*" settings.
 *
 * @ingroup plugins_setting
 */
abstract class CdnCacheTtlBase extends CdnProviderBase {

  /**
   * The DateFormatter service.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  static protected $dateFormatter;

  /**
   * A list of TTL options.
   *
   * @var array
   */
  static protected $ttlOptions;

  /**
   * {@inheritdoc}
   */
  public function alterFormElement(Element $form, FormStateInterface $form_state, $form_id = NULL) {
    $setting = $this->getSettingElement($form, $form_state);
    $setting->setProperty('options', $this->getTtlOptions());

    // @todo This really shouldn't be here, but there isn't a great way of
    // setting this from the provider.
    if ($this->provider->getPluginId() === 'custom') {
      $setting->setProperty('disabled', TRUE);
      $setting->setProperty('description', '');
      $group = $this->getGroupElement($form, $form_state);
      $group->setProperty('description', $this->t('All caching is forced to "Forever" when using the "Custom" CDN Provider. This is because the provided Custom URLs above are used as part of the cache identifier. Anytime the above Custom URLs are modified, all of the caches are rebuilt automatically.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function autoCreateFormElement() {
    // Don't auto create these; they are created as part of CDN Provider.
    // @see \Drupal\bootstrap\Plugin\Setting\Advanced\Cdn\CdnProvider::alterFormElement()
    return FALSE;
  }

  /**
   * Retrieves the TTL options.
   */
  protected function getTtlOptions() {
    if (!isset(static::$ttlOptions)) {
      $dateFormatter = $this->getDateFormatter();
      $intervals = [
        ProviderInterface::TTL_NEVER,
        ProviderInterface::TTL_ONE_DAY,
        ProviderInterface::TTL_ONE_WEEK,
        ProviderInterface::TTL_ONE_MONTH,
        ProviderInterface::TTL_THREE_MONTHS,
        ProviderInterface::TTL_SIX_MONTHS,
        ProviderInterface::TTL_ONE_YEAR,
      ];
      static::$ttlOptions = array_map([$dateFormatter, 'formatInterval'], array_combine($intervals, $intervals));
      static::$ttlOptions[ProviderInterface::TTL_NEVER] = (string) $this->t('Never');
      static::$ttlOptions[ProviderInterface::TTL_FOREVER] = (string) $this->t('Forever');
    }
    return static::$ttlOptions;
  }

  /**
   * Retrieves the DateFormatter service.
   *
   * @return \Drupal\Core\Datetime\DateFormatterInterface
   *   The DateFormatter service.
   */
  protected function getDateFormatter() {
    if (!isset(static::$dateFormatter)) {
      static::$dateFormatter = \Drupal::service('date.formatter');
    }
    return static::$dateFormatter;
  }

}
