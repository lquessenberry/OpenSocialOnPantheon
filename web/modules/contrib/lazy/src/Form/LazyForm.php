<?php

namespace Drupal\lazy\Form;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Condition\ConditionManager;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Render\Markup;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\lazy\LazyInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;

/**
 * Configure Lazy settings for this site.
 */
class LazyForm extends ConfigFormBase {

  /**
   * The Lazy-load service.
   *
   * @var \Drupal\lazy\Lazy
   */
  protected $lazyLoad;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandler
   */
  protected $moduleHandler;

  /**
   * The condition plugin manager.
   *
   * @var \Drupal\Core\Condition\ConditionManager
   */
  protected $conditionManager;


  /**
   * The 'request_path' condition.
   *
   * @var \Drupal\system\Plugin\Condition\RequestPath
   */
  protected $condition;

  /**
   * Constructs a LazyForm object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The configuration factory.
   * @param \Drupal\lazy\LazyInterface $lazy_load
   *   The Lazy-load service.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   * @param \Drupal\Core\Condition\ConditionManager $condition_manager
   *   The condition plugin manager.
   */
  public function __construct(ConfigFactoryInterface $config_factory, LazyInterface $lazy_load, ModuleHandlerInterface $module_handler, ConditionManager $condition_manager) {
    parent::__construct($config_factory);
    $this->lazyLoad = $lazy_load;
    $this->moduleHandler = $module_handler;
    $this->condition = $condition_manager->createInstance('request_path');
  }

  /**
   * Instantiates a new instance of this class.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The service container this instance should use.
   *
   * @return \Drupal\Core\Form\ConfigFormBase|\Drupal\Core\Form\FormBase|static
   *   A static class.
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('lazy'),
      $container->get('module_handler'),
      $container->get('plugin.manager.condition')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'lazy_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return ['lazy.settings', 'image.settings'];
  }

  /**
   * Builds the status report for all enabled filter formats & field formatters.
   */
  protected function getEnabledFiltersAndFields(): void {
    $links = [];

    // Build links of text-formats.
    foreach (filter_formats() as $key => $filter) {
      if (
        $filter->status()
        && ($filter_configuration = $filter->filters()->getConfiguration())
        && isset($filter_configuration['lazy_filter']['status'])
        && $filter_configuration['lazy_filter']['status']
      ) {
        $tags = $filter_configuration['lazy_filter']['settings'];
        $label = '';
        if ($tags['image'] && $tags['iframe']) {
          $label = $this->t('(Images and IFrames)');
        }
        elseif ($tags['image']) {
          $label = $this->t('(Images only)');
        }
        elseif ($tags['iframe']) {
          $label = $this->t('(IFrames only)');
        }

        $links['filter'][$filter->id()] = [
          'title' => $filter->label() . ' ' . $label,
          'url' => Url::fromRoute('entity.filter_format.edit_form', [
            'filter_format' => $filter->id(),
          ], [
            'query' => [
              'destination' => Url::fromRoute('lazy.config_form')->toString(),
            ],
          ]),
        ];
      }
    }

    // Build links for fields.
    $config_keys = $this->configFactory()->listAll('core.entity_view_display.');
    foreach ($config_keys as $config_key) {
      $entity_view_display = $this->config($config_key);
      $content = $entity_view_display->get('content');
      $entity_type = $entity_view_display->get('targetEntityType');
      foreach ($content as $field_name => $field) {
        if (isset($field['third_party_settings']['lazy']['lazy_image']) && ($field['third_party_settings']['lazy']['lazy_image'] == TRUE)) {
          if ($entity_type === 'paragraph') {
            $key = 'paragraphs_type';
          }
          elseif ($entity_type === 'taxonomy_term') {
            $key = 'taxonomy_vocabulary';
          }
          elseif ($entity_type === 'contact_message') {
            $key = 'contact_form';
          }
          else {
            $key = "${entity_type}_type";
          }

          $entity_mode = $entity_view_display->get('mode');
          $entity_bundle = $entity_view_display->get('bundle');
          $field_name_and_mode = "${field_name}-${entity_mode}";
          $link_text = "${entity_type}.${entity_bundle}.${field_name}.${entity_mode}";

          $url_manage_display = $this->moduleHandler->moduleExists('field_ui') ?
            Url::fromRoute("entity.entity_view_display.${entity_type}.view_mode",
              [
                $key => $entity_bundle,
                'view_mode_name' => $entity_mode,
              ],
              [
                'query' => [
                  'destination' => Url::fromRoute('lazy.config_form')->toString(),
                ],
              ]
            ) :
            Url::fromRoute('<nolink>');

          $links['field'][$field_name_and_mode] = [
            'title' => $link_text,
            'url' => $url_manage_display,
          ];
        }
      }
    }

    // Display a message listing all text-formats have lazy-loading enabled.
    $this->addLazyStatusMessage($links, 'filter', $this->t('The <strong>text-formats</strong> have lazy-loading enabled:'));
    // Display a message listing all fields have lazy-loading enabled.
    $this->addLazyStatusMessage($links, 'field', $this->t('The <strong>fields</strong> have lazy-loading enabled:'));
  }

  /**
   * Add a informative message.
   *
   * @param array $links
   *   The array of links.
   * @param string $type
   *   Can be 'filters' or 'fields'.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup $message
   *   The message.
   */
  private function addLazyStatusMessage(array $links, string $type, TranslatableMarkup $message): void {
    $links_result = 'none';
    $message_type = MessengerInterface::TYPE_WARNING;

    if (!empty($links[$type]) && count($links[$type])) {
      $links_result = [];
      foreach ($links[$type] as $link) {
        $links_result[] = Link::fromTextAndUrl(
          $link['title'],
          $link['url']
        )->toString();
      }
      $links_result = implode(', ', $links_result);
      $message_type = MessengerInterface::TYPE_STATUS;
    }

    $this->messenger()->addMessage(
      Markup::create($message . ' ' . $links_result),
      $message_type
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $lazy_settings = $this->config('lazy.settings');

    $this->getEnabledFiltersAndFields();

    $form['preview'] = [
      '#type' => 'details',
      '#title' => $this->t('Preview'),
      '#open' => FALSE,
    ];
    $form['preview']['spacer'] = [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#value' => $this->t('Following empty space is intentional. Scroll down for an image preview.'),
      '#attributes' => [
        'style' => 'height: 3000px;',
      ],
    ];
    $form['preview']['image'] = [
      '#theme' => 'image',
      '#uri' => $this->config('image.settings')->get('preview_image'),
      '#alt' => $this->t('Preview image'),
      '#title' => $this->t('Preview image'),
      '#attributes' => [
        'width' => 480,
        'height' => 360,
        'style' => 'width: 480px; height: 360px;',
        'data-lazy' => TRUE,
      ],
    ];

    $form['settings'] = [
      '#type' => 'vertical_tabs',
      '#title' => $this->t('Settings'),
      '#parents' => ['lazy_tabs'],
    ];
    $form['shared'] = [
      '#type' => 'details',
      '#title' => $this->t('Shared settings'),
      '#group' => 'lazy_tabs',
    ];
    $form['shared']['preferNative'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Prefer native lazy-loading'),
      '#description' => $this->t('If checked and the browser supports, native lazy-loading will be used, otherwise <em>lazysizes</em> library will be used for all browsers.'),
      '#default_value' => $lazy_settings->get('preferNative'),
      '#required' => FALSE,
    ];
    $form['shared']['skipClass'] = [
      '#type' => 'textfield',
      '#title' => $this->t('skipClass'),
      '#description' => $this->t('Elements having this class name will be ignored.'),
      '#default_value' => $lazy_settings->get('skipClass'),
      '#size' => 20,
      '#required' => TRUE,
    ];
    $form['shared']['placeholderSrc'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Placeholder image URL'),
      '#description' => $this->t('Suggestion: 1x1 pixels transparent GIF: <code>@code</code>', [
        '@code' => 'data:image/gif;base64,R0lGODlhAQABAAAAACH5BAEKAAEALAAAAAABAAEAAAICTAEAOw==',
      ]),
      '#default_value' => $lazy_settings->get('placeholderSrc'),
      '#size' => 100,
      '#maxlength' => 255,
      '#required' => FALSE,
    ];
    $form['shared']['cssEffect'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable default CSS effect'),
      '#description' => $this->t('When it is checked the default CSS transition effect is applied with matching class names.'),
      '#default_value' => $lazy_settings->get('cssEffect'),
      '#required' => FALSE,
    ];
    $form['shared']['minified'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Use minified versions.'),
      '#description' => $this->t('When it is checked the minified versions of the library files are used.'),
      '#default_value' => $lazy_settings->get('minified'),
      '#return_value' => 1,
    ];
    $js = $lazy_settings->get('minified');
    if ($js || is_null($js)) {
      $js = '/lazysizes.min.js';
    }
    else {
      $js = '/lazysizes.js';
    }
    $library_path = $lazy_settings->get('libraryPath') ?? '/libraries/lazysizes';
    $form['shared']['libraryPath'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Lazysizes library path, or URL'),
      '#description' => $this->t('For most Drupal instances, the path to the <code>lazysizes</code> plugin would be under the <em>libraries</em> folder in the web root. If you need to serve it from a different local path, or even from an external domain you can define it here:<br><br><b>Examples:</b><br>/libraries/lazysizes<br>/profiles/{your_profile}/libraries/lazysizes<br>https://example.com/libraries/lazysizes'),
      '#field_suffix' => $this->t('<b><a href=":lazysizes">%js</a></b>', [
        ':lazysizes' => $library_path . $js,
        '%js' => $js,
      ]),
      '#default_value' => $library_path,
      '#placeholder' => '/libraries/lazysizes',
      '#required' => TRUE,
    ];

    // Set the default condition configuration.
    $visibility = $lazy_settings->get('visibility') ?? [
      'id' => 'request_path',
      'pages' => $lazy_settings->get('disabled_paths') ?? '/rss.xml',
      'negate' => 0,
    ];
    $this->condition->setConfiguration($visibility);
    $form['visibility'] = [
      '#type' => 'details',
      '#title' => $this->t('Visibility'),
      '#description' => $this->t('This configuration applies to both <em>image fields</em> and <em>inline images/iframes</em> on following pages.'),
      '#group' => 'lazy_tabs',
    ];
    $form += $this->condition->buildConfigurationForm($form, $form_state);
    $form['pages']['#group'] = 'visibility';
    $form['negate']['#group'] = 'visibility';
    $form['negate']['#title'] = $this->t('Enable lazy-loading ONLY on specified pages.');
    $form['negate']['#description'] = $this->t('<p><strong>unchecked</strong> (default): lazy-loading is enabled on ALL pages except the specified pages.</p><p><strong>checked</strong>: lazy-loading is enabled ONLY on the specified pages.</p>');

    $form['visibility']['more'] = [
      '#type' => 'item',
      '#title' => $this->t('Additional settings:'),
      '#markup' => '<hr>',
      '#weight' => 8,
    ];
    $form['visibility']['disable_admin'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Disable lazy-loading for administration pages.'),
      '#default_value' => $lazy_settings->get('disable_admin'),
      '#weight' => 9,
    ];
    $form['visibility']['amp'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Automatically disable lazy-loading for <a href=":url" title="Accelerated Mobile Pages">AMP</a> (pages with <code>?amp</code> in the query-strings).', [
        ':url' => 'https://www.drupal.org/project/amp',
      ]),
      '#default_value' => 1,
      '#disabled' => TRUE,
      '#weight' => 10,
      '#access' => $this->moduleHandler->moduleExists('amp'),
    ];

    $form['lazysizes'] = [
      '#type' => 'details',
      '#title' => $this->t('Lazysizes configuration'),
      '#description' => $this->t('<div class="messages"><strong>lazysizes</strong> is a fast (jank-free), SEO-friendly and self-initializing lazyloader for images (including responsive images <code>picture</code>/<code>srcset</code>), iframes, scripts/widgets and much more. It also prioritizes resources by differentiating between crucial in view and near view elements to make perceived performance even faster.</div><p>The plugin can be configured with the following options. Check out the <a href=":repo">official repository</a> for <a href=":doc">documentation</a> and <a href=":examples">examples</a>.</p>',
        [
          ':repo' => 'https://github.com/aFarkas/lazysizes',
          ':doc' => 'https://github.com/aFarkas/lazysizes/blob/gh-pages/README.md',
          ':examples' => 'http://afarkas.github.io/lazysizes/#examples',
        ]
      ),
      '#group' => 'lazy_tabs',
    ];
    $form['lazysizes']['lazysizes_lazyClass'] = [
      '#type' => 'textfield',
      '#title' => $this->t('lazyClass'),
      '#description' => $this->t('Marker class for all elements which should be lazy loaded.'),
      '#default_value' => $lazy_settings->get('lazysizes.lazyClass'),
      '#required' => TRUE,
    ];
    $form['lazysizes']['lazysizes_loadedClass'] = [
      '#type' => 'textfield',
      '#title' => $this->t('loadedClass'),
      '#description' => $this->t('This class will be added to any element as soon as the image is loaded or the image comes into view. Can be used to add unveil effects or to apply styles.'),
      '#default_value' => $lazy_settings->get('lazysizes.loadedClass'),
      '#required' => TRUE,
    ];
    $form['lazysizes']['lazysizes_loadingClass'] = [
      '#type' => 'textfield',
      '#title' => $this->t('loadingClass'),
      '#description' => $this->t('This class will be added to img element as soon as image loading starts. Can be used to add unveil effects.'),
      '#default_value' => $lazy_settings->get('lazysizes.loadingClass'),
      '#required' => TRUE,
    ];
    $form['lazysizes']['lazysizes_preloadClass'] = [
      '#type' => 'textfield',
      '#title' => $this->t('preloadClass'),
      '#description' => $this->t('Marker class for elements which should be lazy pre-loaded after onload. Those elements will be even preloaded, if the <code>preloadAfterLoad</code> option is set to <code>false</code>.'),
      '#default_value' => $lazy_settings->get('lazysizes.preloadClass'),
      '#required' => TRUE,
    ];
    $form['lazysizes']['lazysizes_errorClass'] = [
      '#type' => 'textfield',
      '#title' => $this->t('errorClass'),
      '#description' => $this->t('The error class if image fails to load'),
      '#default_value' => $lazy_settings->get('lazysizes.errorClass'),
      '#required' => TRUE,
    ];
    $form['lazysizes']['lazysizes_autosizesClass'] = [
      '#type' => 'textfield',
      '#title' => $this->t('autosizesClass'),
      '#description' => '',
      '#default_value' => $lazy_settings->get('lazysizes.autosizesClass'),
      '#required' => TRUE,
    ];
    $form['lazysizes']['lazysizes_srcAttr'] = [
      '#type' => 'textfield',
      '#title' => $this->t('srcAttr'),
      '#description' => $this->t('The attribute, which should be transformed to <code>src</code>.'),
      '#default_value' => $lazy_settings->get('lazysizes.srcAttr'),
      '#required' => TRUE,
    ];
    $form['lazysizes']['lazysizes_srcsetAttr'] = [
      '#type' => 'textfield',
      '#title' => $this->t('srcsetAttr'),
      '#description' => $this->t('The attribute, which should be transformed to <code>srcset</code>.'),
      '#default_value' => $lazy_settings->get('lazysizes.srcsetAttr'),
      '#required' => TRUE,
    ];
    $form['lazysizes']['lazysizes_sizesAttr'] = [
      '#type' => 'textfield',
      '#title' => $this->t('sizesAttr'),
      '#description' => $this->t('The attribute, which should be transformed to <code>sizes</code>. Makes almost only makes sense with the value <code>"auto"</code>. Otherwise, the <code>sizes</code> attribute should be used directly.'),
      '#default_value' => $lazy_settings->get('lazysizes.sizesAttr'),
      '#required' => TRUE,
    ];
    $form['lazysizes']['lazysizes_minSize'] = [
      '#type' => 'number',
      '#title' => $this->t('minSize'),
      '#description' => $this->t('For <code>data-sizes="auto"</code> feature. The minimum size of an image that is used to calculate the <code>sizes</code> attribute. In case it is under <code>minSize</code> the script traverses up the DOM tree until it finds a parent that is over <code>minSize</code>.'),
      '#default_value' => $lazy_settings->get('lazysizes.minSize'),
      '#required' => TRUE,
      '#attributes' => [
        'min' => 0,
      ],
    ];
    $form['lazysizes']['lazysizes_customMedia'] = [
      '#type' => 'textarea',
      '#title' => $this->t('customMedia'),
      '#description' => $this->t('The <code>customMedia</code> option object is an alias map for different media queries. It can be used to separate/centralize your multiple specific media queries implementation (layout) from the <code>source[media]</code> attribute (content/structure) by creating labeled media queries.'),
      '#default_value' => json_encode($lazy_settings->get('lazysizes.customMedia'), JSON_FORCE_OBJECT),
      '#required' => TRUE,
      '#rows' => 1,
    ];
    $form['lazysizes']['lazysizes_init'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('init'),
      '#description' => $this->t('By default lazysizes initializes itself, to load in view assets as soon as possible. In the unlikely case you need to setup/configure something with a later script you can set this option to <code>false</code> and call <code>lazySizes.init();</code> later explicitly.'),
      '#default_value' => $lazy_settings->get('lazysizes.init'),
      '#required' => FALSE,
    ];
    $form['lazysizes']['lazysizes_expFactor'] = [
      '#type' => 'number',
      '#title' => $this->t('expFactor'),
      '#description' => $this->t('The <code>expFactor</code> is used to calculate the "preload expand", by multiplying the normal <code>expand</code> with the <code>expFactor</code> which is used to preload assets while the browser is idling (no important network traffic and no scrolling). (Reasonable values are between <code>1.5</code> and <code>4</code> depending on the <code>expand</code> option).'),
      '#default_value' => $lazy_settings->get('lazysizes.expFactor'),
      '#required' => TRUE,
      '#min' => 0,
      '#step' => 0.1,
    ];
    $form['lazysizes']['lazysizes_hFac'] = [
      '#type' => 'number',
      '#title' => $this->t('hFac'),
      '#description' => $this->t('The <code>hFac</code> (horizontal factor) modifies the horizontal expand by multiplying the <code>expand</code> value with the <code>hFac</code> value. Use case: In case of carousels there is often the wish to make the horizontal expand narrower than the normal vertical expand option. Reasonable values are between 0.4 - 1. In the unlikely case of a horizontal scrolling website also 1 - 1.5.'),
      '#default_value' => $lazy_settings->get('lazysizes.hFac'),
      '#required' => TRUE,
      '#min' => 0,
      '#step' => 0.1,
    ];
    $form['lazysizes']['lazysizes_loadMode'] = [
      '#type' => 'number',
      '#title' => $this->t('loadMode'),
      '#description' => $this->t("The <code>loadMode</code> can be used to constrain the allowed loading mode. Possible values are 0 = don't load anything, 1 = only load visible elements, 2 = load also very near view elements (<code>expand</code> option) and 3 = load also not so near view elements (<code>expand</code> * <code>expFactor</code> option). This value is automatically set to <code>3</code> after onload. Change this value to <code>1</code> if you (also) optimize for the onload event or change it to <code>3</code> if your onload event is already heavily delayed."),
      '#default_value' => $lazy_settings->get('lazysizes.loadMode'),
      '#required' => TRUE,
      '#min' => 0,
    ];
    $form['lazysizes']['lazysizes_loadHidden'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('loadHidden'),
      '#description' => $this->t('Whether to load <code>visibility: hidden</code> elements. Important: lazySizes will load hidden images always delayed. If you want them to be loaded as fast as possible you can use <code>opacity: 0.001</code> but never <code>visibility: hidden</code> or <code>opacity: 0</code>.'),
      '#default_value' => $lazy_settings->get('lazysizes.loadHidden'),
      '#required' => FALSE,
    ];
    $form['lazysizes']['lazysizes_ricTimeout'] = [
      '#type' => 'number',
      '#title' => $this->t('ricTimeout'),
      '#description' => $this->t('The timeout option used for the <code>requestIdleCallback</code>. Reasonable values between: 0, 100 - 1000. (Values below 50 disable the <code>requestIdleCallback</code> feature.)'),
      '#default_value' => $lazy_settings->get('lazysizes.ricTimeout'),
      '#required' => TRUE,
      '#min' => 0,
    ];
    $form['lazysizes']['lazysizes_throttleDelay'] = [
      '#type' => 'number',
      '#title' => $this->t('throttleDelay'),
      '#description' => $this->t('The timeout option used to throttle all listeners. Reasonable values between: 66 - 200.'),
      '#default_value' => $lazy_settings->get('lazysizes.throttleDelay'),
      '#required' => TRUE,
      '#min' => 0,
    ];

    $plugins = array_keys($this->lazyLoad->getPlugins());
    $form['lazysizes']['lazysizes_plugins'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Plugins'),
      '#description' => $this->t('Review the <a href=":doc">documentation</a> before enabling any of the plugins. Enabled plugins may offer additional configuration which you can override via <code>:code</code>', [
        ':doc' => 'https://github.com/aFarkas/lazysizes#plugins',
        ':code' => 'window.lazySizesConfig',
      ]),
      '#options' => array_combine($plugins, $plugins),
      '#default_value' => $lazy_settings->get('lazysizes.plugins'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * Check if the provided string is a valid JSON object.
   *
   * @param string $string
   *   JSON data as string.
   *
   * @return bool
   *   Returns true if string is a valid JSON, false otherwise.
   */
  private function isJson($string): bool {
    json_decode($string, FALSE);
    return (json_last_error() == JSON_ERROR_NONE);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);

    if (($custom_media = $form_state->getValue('lazysizes_customMedia')) && !$this->isJson($custom_media)) {
      $form_state->setErrorByName('lazysizes_customMedia', $this->t('Not a valid JavaScript object.'));
    }

    $library_path = $form_state->getValue('libraryPath');
    if (substr_compare($library_path, '/', 0, 1, TRUE) !== 0 && substr_compare($library_path, 'https://', 0, 8, TRUE) !== 0) {
      $form_state->setErrorByName('libraryPath', $this->t('The path must either start with <code>/</code> or <code>https://</code>'));
    }
    if (substr_compare($library_path, '/', -1, 1, TRUE) === 0) {
      $form_state->setErrorByName('libraryPath', $this->t('The path must not have a trailing forward slash.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->condition->submitConfigurationForm($form, $form_state);

    $value = $form_state->getValues();
    $this->configFactory()->getEditable('lazy.settings')
      ->set('skipClass', $value['skipClass'])
      ->set('disable_admin', (bool) $value['disable_admin'])
      ->set('visibility', $this->condition->getConfiguration())
      ->set('placeholderSrc', $value['placeholderSrc'])
      ->set('preferNative', (bool) $value['preferNative'])
      ->set('cssEffect', (bool) $value['cssEffect'])
      ->set('minified', (bool) $value['minified'])
      ->set('libraryPath', $value['libraryPath'])

      ->set('lazysizes.lazyClass', $value['lazysizes_lazyClass'])
      ->set('lazysizes.loadedClass', $value['lazysizes_loadedClass'])
      ->set('lazysizes.loadingClass', $value['lazysizes_loadingClass'])
      ->set('lazysizes.preloadClass', $value['lazysizes_preloadClass'])
      ->set('lazysizes.errorClass', $value['lazysizes_errorClass'])
      ->set('lazysizes.autosizesClass', $value['lazysizes_autosizesClass'])
      ->set('lazysizes.srcAttr', $value['lazysizes_srcAttr'])
      ->set('lazysizes.srcsetAttr', $value['lazysizes_srcsetAttr'])
      ->set('lazysizes.sizesAttr', $value['lazysizes_sizesAttr'])
      ->set('lazysizes.minSize', (int) $value['lazysizes_minSize'])
      ->set('lazysizes.customMedia', JSON::decode($value['lazysizes_customMedia']))
      ->set('lazysizes.init', (bool) $value['lazysizes_init'])
      ->set('lazysizes.expFactor', (float) $value['lazysizes_expFactor'])
      ->set('lazysizes.hFac', (float) $value['lazysizes_hFac'])
      ->set('lazysizes.loadMode', (int) $value['lazysizes_loadMode'])
      ->set('lazysizes.loadHidden', (bool) $value['lazysizes_loadHidden'])
      ->set('lazysizes.ricTimeout', (int) $value['lazysizes_ricTimeout'])
      ->set('lazysizes.throttleDelay', (int) $value['lazysizes_throttleDelay'])
      ->set('lazysizes.plugins', array_filter($value['lazysizes_plugins']))
      ->save(TRUE);

    parent::submitForm($form, $form_state);
  }

}
