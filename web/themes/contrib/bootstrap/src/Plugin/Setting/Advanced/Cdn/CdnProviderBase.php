<?php

namespace Drupal\bootstrap\Plugin\Setting\Advanced\Cdn;

/**
 * Due to BC reasons, this class cannot be moved.
 *
 * @todo Move namespace up one.
 */

use Drupal\bootstrap\Bootstrap;
use Drupal\bootstrap\Plugin\Provider\ProviderInterface;
use Drupal\bootstrap\Plugin\ProviderManager;
use Drupal\bootstrap\Plugin\Setting\SettingBase;
use Drupal\bootstrap\Traits\FormAutoloadFixTrait;
use Drupal\bootstrap\Utility\Element;
use Drupal\Component\Utility\Html;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * A base class for CDN Provider settings.
 *
 * @ingroup plugins_provider
 * @ingroup plugins_setting
 */
abstract class CdnProviderBase extends SettingBase {

  use FormAutoloadFixTrait;

  /**
   * The active provider based on form value or theme setting.
   *
   * @var \Drupal\bootstrap\Plugin\Provider\ProviderInterface
   */
  protected $provider;

  /**
   * {@inheritdoc}
   */
  public function alterForm(array &$form, FormStateInterface $form_state, $form_id = NULL) {
    // Add autoload fix to make sure AJAX callbacks work.
    static::formAutoloadFix($form_state);

    // Attempt to extract the active provider from submitted values. Note: in
    // some cases, it needs to be extracted from the raw input if the values
    // haven't yet been populated.
    $input = $form_state->getUserInput();
    $provider = $form_state->getValue('cdn_provider', isset($input['cdn_provider']) ? Html::escape($input['cdn_provider']) : NULL);
    $this->provider = ProviderManager::load($this->theme, $provider);

    // Invoke the original alter.
    parent::alterForm($form, $form_state, $form_id);
  }

  /**
   * Handles any CDN Provider exceptions that may have been thrown.
   *
   * @param \Drupal\bootstrap\Plugin\Provider\ProviderInterface $provider
   *   A CDN Provider to check.
   * @param bool $reset
   *   Flag indicating whether to remove the Exceptions once they have been
   *   retrieved.
   *
   * @return bool
   *   TRUE if there are exceptions, FALSE otherwise.
   */
  protected static function checkCdnExceptions(ProviderInterface $provider, $reset = TRUE) {
    $exceptions = $provider->getCdnExceptions($reset);
    if ($exceptions) {
      \Drupal::messenger()->addMessage(t('Unable to parse @provider data. <a href=":logs">Check the logs for more details.</a> If your issues are network related, consider using the "custom" CDN Provider instead to statically set the URLs that should be used.', [
        ':logs' => Url::fromRoute('dblog.overview')->toString(),
        '@provider' => $provider->getLabel(),
      ]), 'error');
      foreach ($exceptions as $exception) {
        watchdog_exception('bootstrap', $exception);
      }
    }
    return !!$exceptions;
  }

  /**
   * AJAX callback for reloading CDN Providers.
   *
   * @param array $form
   *   Nested array of form elements that comprise the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return array
   *   The form element to render.
   */
  public static function ajaxCdnProvidersCallback(array $form, FormStateInterface $form_state) {
    return $form['cdn']['cdn_provider'];
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags() {
    return ['library_info'];
  }

  /**
   * Retrieves the active CDN Provider.
   *
   * @return \Drupal\bootstrap\Plugin\Provider\ProviderInterface
   *   A CDN Provider.
   */
  protected function getProvider() {
    if (!isset($this->provider)) {
      $this->provider = $this->theme->getCdnProvider();
    }
    return $this->provider;
  }

  /**
   * Sets the #ajax property to rebuild the entire CDN Providers container.
   *
   * @param \Drupal\bootstrap\Utility\Element|array $element
   *   An Element to modify.
   *
   * @return \Drupal\bootstrap\Utility\Element
   *   The Element passed.
   */
  protected function setCdnProvidersAjax($element) {
    return Element::create($element)->setProperty('ajax', [
      'callback' => [get_class($this), 'ajaxCdnProvidersCallback'],
      'wrapper' => 'cdn-providers',
    ]);
  }

}
