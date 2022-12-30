<?php

namespace Drupal\select2_facets\Plugin\facets\widget;

use Drupal\Component\Utility\Crypt;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\KeyValueStore\KeyValueStoreInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Site\Settings;
use Drupal\facets\FacetInterface;
use Drupal\facets\Widget\WidgetPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * The select2 widget.
 *
 * @FacetsWidget(
 *   id = "select2",
 *   label = @Translation("Select2"),
 *   description = @Translation("A configurable widget that shows a select2."),
 * )
 */
class Select2Widget extends WidgetPluginBase implements ContainerFactoryPluginInterface {

  /**
   * The current request.
   *
   * @var \Symfony\Component\HttpFoundation\Request
   */
  protected $request;

  /**
   * The key-value store for entity_autocomplete.
   *
   * @var \Drupal\Core\KeyValueStore\KeyValueStoreInterface
   */
  protected $keyValueStore;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, Request $request, KeyValueStoreInterface $key_value_store) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->request = $request;
    $this->keyValueStore = $key_value_store;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('request_stack')->getCurrentRequest(),
      $container->get('keyvalue')->get('entity_autocomplete')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'autocomplete' => FALSE,
      'match_operator' => 'CONTAINS',
      'width' => '100%',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function build(FacetInterface $facet) {
    $this->facet = $facet;

    $items = [];
    $active_items = [];
    foreach ($facet->getResults() as $result) {
      if (empty($result->getUrl())) {
        continue;
      }

      $count = $result->getCount();
      $this->showNumbers = $this->getConfiguration()['show_numbers'] && ($count !== NULL);
      $items[$result->getUrl()->toString()] = ($this->showNumbers ? sprintf('%s (%d)', $result->getDisplayValue(), $result->getCount()) : $result->getDisplayValue());
      if ($result->isActive()) {
        $active_items[] = $result->getUrl()->toString();
      }
    }

    $element = [
      '#type' => 'select2',
      '#options' => $items,
      '#required' => FALSE,
      '#value' => $active_items,
      '#multiple' => !$facet->getShowOnlyOneResult(),
      '#autocomplete' => $this->getConfiguration()['autocomplete'],
      '#name' => $facet->getName(),
      '#title' => $facet->get('show_title') ? $facet->getName() : '',
      '#attributes' => [
        'data-drupal-facet-id' => $facet->id(),
        'data-drupal-selector' => 'facet-' . $facet->id(),
        'class' => ['js-facets-select2', 'js-facets-widget'],
      ],
      '#attached' => [
        'library' => ['select2_facets/drupal.select2_facets.select2-widget'],
      ],
      '#cache' => [
        'contexts' => [
          'url.path',
          'url.query_args',
        ],
      ],
      '#select2' => [
        'width' => $this->getConfiguration()['width'],
      ],
    ];

    if ($element['#autocomplete']) {
      $element['#autocomplete_route_callback'] = [
        $this,
        'processFacetAutocomplete',
      ];
    }

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state, FacetInterface $facet) {
    $form = parent::buildConfigurationForm($form, $form_state, $facet);
    $form['width'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Field width'),
      '#default_value' => $this->getConfiguration()['width'],
      '#description' => $this->t("Define a width for the select2 field. It can be either 'element', 'computedstyle', 'style', 'resolve' or any possible CSS unit. E.g. 500px, 50%, 200em. See the <a href='https://select2.org/appearance#container-width'>select2 documentation</a> for further explanations."),
      '#required' => TRUE,
      '#size' => '12',
      '#pattern' => "([0-9]*\.[0-9]+|[0-9]+)(cm|mm|in|px|pt|pc|em|ex|ch|rem|vm|vh|vmin|vmax|%)|element|computedstyle|style|resolve|auto|initial|inherit",
    ];
    $form['autocomplete'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Autocomplete'),
      '#default_value' => $this->getConfiguration()['autocomplete'],
      '#description' => $this->t('Options will be lazy loaded. This is recommended for lists with a lot of values.'),
    ];
    $form['match_operator'] = [
      '#type' => 'radios',
      '#title' => $this->t('Autocomplete matching'),
      '#default_value' => $this->getConfiguration()['match_operator'],
      '#options' => $this->getMatchOperatorOptions(),
      '#description' => $this->t('Select the method used to collect autocomplete suggestions. Note that <em>Contains</em> can cause performance issues on sites with thousands of entities.'),
      '#states' => [
        'visible' => [
          ':input[name$="widget_config[autocomplete]"]' => ['checked' => TRUE],
        ],
      ],
    ];

    return $form;
  }

  /**
   * Returns the options for the match operator.
   *
   * @return array
   *   List of options.
   */
  protected function getMatchOperatorOptions() {
    return [
      'STARTS_WITH' => $this->t('Starts with'),
      'CONTAINS' => $this->t('Contains'),
    ];
  }

  /**
   * Set the autocomplete route properties.
   *
   * @param array $element
   *   The render element.
   *
   * @return array
   *   The render element with autocomplete settings.
   */
  public function processFacetAutocomplete(array &$element) {
    $selection_settings = [
      'path' => $this->request->getUri(),
      'match_operator' => $this->getConfiguration()['match_operator'],
      'show_numbers' => $this->getConfiguration()['show_numbers'],
    ];

    // Store the selection settings in the key/value store and pass a hashed key
    // in the route parameters.
    $data = serialize($selection_settings) . $this->facet->getFacetSourceId() . $this->facet->id();
    $selection_settings_key = Crypt::hmacBase64($data, Settings::getHashSalt());
    if (!$this->keyValueStore->has($selection_settings_key)) {
      $this->keyValueStore->set($selection_settings_key, $selection_settings);
    }

    $element['#autocomplete_route_name'] = 'select2_facets.facet_autocomplete';
    $element['#autocomplete_route_parameters'] = [
      'facetsource_id' => $this->facet->getFacetSourceId(),
      'facet_id' => $this->facet->id(),
      'selection_settings_key' => $selection_settings_key,
    ];

    return $element;
  }

}
