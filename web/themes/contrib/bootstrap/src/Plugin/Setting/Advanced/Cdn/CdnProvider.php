<?php

namespace Drupal\bootstrap\Plugin\Setting\Advanced\Cdn;

/**
 * Due to BC reasons, this class cannot be moved.
 *
 * @todo Move namespace up one.
 */

use Drupal\bootstrap\Bootstrap;
use Drupal\bootstrap\Plugin\Form\FormInterface;
use Drupal\bootstrap\Plugin\Form\SystemThemeSettings;
use Drupal\bootstrap\Plugin\Provider\Broken;
use Drupal\bootstrap\Plugin\Provider\ProviderInterface;
use Drupal\bootstrap\Plugin\ProviderManager;
use Drupal\bootstrap\Utility\Element;
use Drupal\Component\Utility\Html;
use Drupal\Core\Access\AccessResultAllowed;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Render\Markup;
use Drupal\Core\Url;

/**
 * The "cdn_provider" theme setting.
 *
 * @ingroup plugins_provider
 * @ingroup plugins_setting
 *
 * @BootstrapSetting(
 *   id = "cdn_provider",
 *   type = "select",
 *   title = @Translation("CDN Provider"),
 *   description = @Translation("Choose the CDN Provider used to load Bootstrap resources."),
 *   defaultValue = "jsdelivr",
 *   empty_option = @Translation("None (compile locally)"),
 *   empty_value = "",
 *   weight = -1,
 *   groups = {
 *     "cdn" = @Translation("CDN (Content Delivery Network)"),
 *     "cdn_provider" = false,
 *   },
 *   options = { },
 * )
 */
class CdnProvider extends CdnProviderBase {

  /**
   * {@inheritdoc}
   */
  public function alterForm(array &$form, FormStateInterface $form_state, $form_id = NULL) {
    parent::alterForm($form, $form_state, $form_id);

    // Allow the provider to participate.
    if ($this->provider instanceof FormInterface) {
      $this->provider->alterForm($form, $form_state);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function alterFormElement(Element $form, FormStateInterface $form_state, $form_id = NULL) {
    // Wrap the default group so it can be replaced via AJAX.
    $group = $this->getGroupElement($form, $form_state);
    $group->setProperty('prefix', '<div id="cdn-providers">');
    $group->setProperty('suffix', '</div>');

    // Set available CDN Providers.
    $setting = $this->setCdnProvidersAjax($this->getSettingElement($form, $form_state));
    $setting->setProperty('options', array_map(function (ProviderInterface $provider) {
      return $provider->getLabel();
    }, $this->theme->getCdnProviders()));

    // Add the CDN Provider description.
    $provider = $this->getProvider();
    $description = $provider->getDescription();
    $group->description = [
      '#access' => AccessResultAllowed::allowedIf(!empty((string) $description) && !($provider instanceof Broken)),
      '#type' => 'container',
      '#theme_wrappers' => ['container__help_block'],
      0 => ['#markup' => $description],
    ];

    // Add CDN Provider cache reset functionality.
    $group->cache = [
      '#access' => AccessResultAllowed::allowedIf(!($provider instanceof Broken)),
      '#type' => 'details',
      '#title' => $this->t('Advanced Cache'),
      '#description' => $this->t('All @provider data is intelligently and automatically cached using the various settings below. This allows the @provider data to persist through cache rebuilds. This data will invalidate and rebuild automatically, however a manual reset can be invoked below.', [
        '@provider' => $provider->getPluginId() === 'custom' ? $this->t('CDN Provider') : $provider->getLabel(),
      ]),
      '#weight' => 1000,
    ];

    $ttl_settings = [
      'cdn_cache_ttl_versions',
      'cdn_cache_ttl_themes',
      'cdn_cache_ttl_assets',
      'cdn_cache_ttl_library',
    ];

    // Because these settings are used for all providers, any current value set
    // in the input array is a result of a provider switch via AJAX. Go ahead
    // and unset the value from the current form state and then add the setting
    // to the form.
    $input = $form_state->getUserInput();
    $values = $form_state->getValues();
    foreach ($ttl_settings as $ttl_setting) {
      if (!empty($input['_triggering_element_name']) && $input['_triggering_element_name'] === 'cdn_provider') {
        unset($input[$ttl_setting], $values[$ttl_setting]);
      }
      $this->theme->getSettingPlugin($ttl_setting)->alterForm($form->getArray(), $form_state);
    }
    $form_state->setUserInput($input);
    $form_state->setValues($values);

    $group->cache->reset = $this->setCdnProvidersAjax(Element::createStandalone([
      '#weight' => 100,
      '#type' => 'submit',
      '#description' => $this->t('Note: this will not reset any cached HTTP requests; see the "Advanced" section.'),
      '#value' => $this->t('Reset @provider Cache', [
        '@provider' => $provider->getLabel(),
      ]),
      '#submit' => [
        [get_class($this), 'submitResetProviderCache'],
      ],
    ]));

    // Intercept possible manual import of API data via AJAX callback.
    // @todo Import functionality is deprecated, remove in a future release.
    $this->importProviderData($group, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public static function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);
    $theme = SystemThemeSettings::getTheme(Element::create($form), $form_state);
    $provider = ProviderManager::load($theme, $form_state->getValue('cdn_provider'));
    if ($provider instanceof FormInterface) {
      $provider->submitForm($form, $form_state);
    }
  }

  /**
   * Submit callback for resetting CDN Provider cache.
   *
   * @param array $form
   *   Nested array of form elements that comprise the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public static function submitResetProviderCache(array $form, FormStateInterface $form_state) {
    $form_state->setRebuild();
    $theme = SystemThemeSettings::getTheme(Element::create($form), $form_state);
    $provider = ProviderManager::load($theme, $form_state->getValue('cdn_provider', $theme->getSetting('cdn_provider')));
    $provider->resetCache();
  }

  /**
   * {@inheritdoc}
   */
  public static function validateFormElement(Element $form, FormStateInterface $form_state) {
    parent::validateFormElement($form, $form_state);
    $theme = SystemThemeSettings::getTheme($form, $form_state);
    $provider = ProviderManager::load($theme, $form_state->getValue('cdn_provider'));

    // Validate the provider.
    if (!($provider instanceof Broken)) {
      $cdnVersion = $form_state->getValue('cdn_version', $theme->getSetting('cdn_version', Bootstrap::FRAMEWORK_VERSION));
      $cdnTheme = $form_state->getValue('cdn_theme', $theme->getSetting('cdn_theme'));
      $assets = $provider->getCdnAssets($cdnVersion, $cdnTheme);

      // Now validate that each asset is reachable.
      $unreachable = [];
      foreach ($assets->toArray() as $asset) {
        $url = $asset->getUrl();
        if (!Bootstrap::checkUrlIsReachable($url)) {
          $unreachable[] = Link::fromTextAndUrl($url, Url::fromUri($url)->setOption('attributes', ['target' => '_blank']));
        }
      }

      if ($unreachable) {
        $form_state->setErrorByName('cdn_provider', t('Unable to reach the following @provider assets: <ul><li>@unreachable</li>', [
          '@provider' => $provider->getLabel(),
          '@unreachable' => Markup::create(implode('</li><li>', $unreachable)),
        ]));
        return;
      }

      // Check for exceptions (API HTTP request errors).
      if (static::checkCdnExceptions($provider)) {
        $form_state->setErrorByName('cdn_provider', t('Unable to use @provider assets. Please choose a different CDN Provider.', [
          '@provider' => $provider->getLabel(),
        ]));
        return;
      }
    }

    if ($provider instanceof FormInterface) {
      $provider->validateFormElement($form, $form_state);
    }
  }

  /**
   * Imports data for a provider that was manually uploaded in theme settings.
   *
   * @param \Drupal\bootstrap\Utility\Element $group
   *   The setting group Element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @todo Import functionality is deprecated, remove in a future release.
   */
  protected function importProviderData(Element $group, FormStateInterface $form_state) {
    if ($form_state->getValue('clicked_button') === $this->t('Save provider data')->render()) {
      $provider_path = ProviderManager::FILE_PATH;

      // FILE_CREATE_DIRECTORY = 1 | FILE_MODIFY_PERMISSIONS = 2.
      $options = 1 | 2;
      if ($fileSystem = \Drupal::service('file_system')) {
        $fileSystem->prepareDirectory($provider_path, $options);
      }

      $provider = $form_state->getValue('cdn_provider', $this->theme->getSetting('cdn_provider'));
      $file = "$provider_path/$provider.json";

      if ($import_data = $form_state->getValue('cdn_provider_import_data', FALSE)) {
        // FILE_EXISTS_REPLACE = 1.
        $replace = 1;
        if ($fileSystem = \Drupal::service('file_system')) {
          $fileSystem->saveData($import_data, $file, $replace);
        }
      }
      elseif ($file && file_exists($file)) {
        if ($fileSystem = \Drupal::service('file_system')) {
          $fileSystem->delete($file);
        }
      }

      // Clear the cached definitions so they can get rebuilt.
      $providerManager = new ProviderManager($this->theme);
      $providerManager->clearCachedDefinitions();
      $form_state->setRebuild();
      return;
    }

    $provider = $this->getProvider();
    $plugin_id = Html::cleanCssIdentifier($provider->getPluginId());

    // To avoid triggering unnecessary deprecation messages, extract these
    // values from the provider definition directly.
    // @todo Import functionality is deprecated, remove in a future release.
    $definition = $provider->getPluginDefinition();
    $hasError = !empty($definition['error']);
    $isImported = !empty($definition['imported']);

    // Indicate there was an error retrieving the provider's API data.
    if ($hasError || $isImported) {
      if ($isImported) {
        Bootstrap::deprecated('\Drupal\bootstrap\Plugin\Provider\ProviderInterface::isImported');
      }
      if ($hasError) {
        // Now a deprecation message can be shown as the provider clearly is
        // using the outdated "process definition" method of providing assets.
        Bootstrap::deprecated('\Drupal\bootstrap\Plugin\Provider\ProviderInterface::hasError');
        $description_label = $this->t('ERROR');
        $description = $this->t('Unable to reach or parse the data provided by the @title API. Ensure the server this website is hosted on is able to initiate HTTP requests. If the request consistently fails, it is likely that there are certain PHP functions that have been disabled by the hosting provider for security reasons. It is possible to manually copy and paste the contents of the following URL into the "Imported @title data" section below.<br /><br /><a href=":provider_api" target="_blank">:provider_api</a>.', [
          '@title' => $provider->getLabel(),
          ':provider_api' => $provider->getApi(),
        ]);
        $group->error = [
          '#markup' => '<div class="alert alert-danger messages error"><strong>' . $description_label . ':</strong> ' . $description . '</div>',
          '#weight' => -20,
        ];
      }

      $group->import = [
        '#type' => 'details',
        '#title' => $this->t('Imported @title data', ['@title' => $provider->getLabel()]),
        '#description' => $this->t('The provider will attempt to parse the data entered here each time it is saved. If no data has been entered, any saved files associated with this provider will be removed and the provider will again attempt to request the API data normally through the following URL: <a href=":provider_api" target="_blank">:provider_api</a>.', [
          ':provider_api' => $provider->getPluginDefinition()['api'],
        ]),
        '#weight' => 10,
        '#open' => FALSE,
      ];

      $group->import->cdn_provider_import_data = [
        '#type' => 'textarea',
        '#default_value' => file_exists(ProviderManager::FILE_PATH . '/' . $plugin_id . '.json') ? file_get_contents(ProviderManager::FILE_PATH . '/' . $plugin_id . '.json') : NULL,
      ];

      $group->import->submit = $this->setCdnProvidersAjax([
        '#type' => 'submit',
        '#value' => $this->t('Save provider data'),
        '#executes_submit_callback' => FALSE,
      ]);
    }
  }

}
