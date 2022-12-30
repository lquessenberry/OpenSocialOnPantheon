<?php

namespace Drupal\select2\Element;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Entity\Element\EntityAutocomplete;
use Drupal\Core\Form\FormState;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\OptGroup;
use Drupal\Core\Render\Element\Select;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\select2\Select2Trait;

/**
 * Provides an select2 form element.
 *
 * Properties:
 * - #cardinality: (optional) How many options can be selected. Default is
 *   unlimited.
 * - #autocomplete_options_callback: (optional) A callback to return all valid
 *   currently selected options. @see static::getValidSelectedOptions().
 * - #autocomplete_route_callback: (optional) A callback that sets the
 *   #autocomplete_route_name and autocomplete_route_parameters keys on the
 *   render element. @see static::setAutocompleteRouteParameters().
 *
 * Simple usage example:
 * @code
 *   $form['example_select'] = [
 *     '#type' => 'select2',
 *     '#title' => $this->t('Select element'),
 *     '#options' => [
 *       '1' => $this->t('One'),
 *       '2' => [
 *         '2.1' => $this->t('Two point one'),
 *         '2.2' => $this->t('Two point two'),
 *       ],
 *       '3' => $this->t('Three'),
 *     ],
 *   ];
 *
 * If you want to prevent the rendering of all options and fetch the options via
 * ajax instead, you can use the '#autocomplete' property. It's also needed to
 * specify which entities are available with '#target_type',
 * '#selection_handler' and '#selection_settings'.
 * @code
 *   $form['my_element'] = [
 *     '#type' => 'select2',
 *     '#title' => $this->t('Select element'),
 *     '#options' => [
 *       '1' => $this->t('One'),
 *       '2' => $this->t('Two'),
 *       '3' => $this->t('Three'),
 *     ],
 *     '#autocomplete' => TRUE,
 *     '#target_type' => 'node',
 *     // The selection handler is optional and pre-populated to 'default'.
 *     '#selection_handler' => 'default',
 *     '#selection_settings' => [
 *       'target_bundles' => ['article', 'page'],
 *     ],
 *   ];
 *
 * If you want to allow an input of an entity label that does not exist yet but
 * can be created "on the fly" on form submission, the '#autocreate' property
 * can be used:
 * @code
 *   // #autocreate should be an array where the 'bundle' key is required and
 *   // should be the bundle name for the new entity.
 *   // The 'uid' key of the #autocreate array is optional and defaults to the
 *   // current logged-in user. It should be the user ID for the new entity,
 *   // if the target entity type implements \Drupal\user\EntityOwnerInterface.
 *   $form['my_element'] = [
 *     '#type' => 'select2',
 *     '#target_type' => 'taxonomy_term',
 *     '#autocreate' => [
 *       'bundle' => 'tags',
 *       'uid' => <a valid user ID>,
 *     ],
 *   ];
 *
 * The render element sets a bunch of default values to configure the select2
 * element. Nevertheless all select2 config values can be overwritten with the
 * '#select2' property.
 * @code
 *   $form['my_element'] = [
 *     '#type' => 'select2',
 *     '#select2' => [
 *       'allowClear' => TRUE,
 *     ],
 *   ];
 *
 * @see https://select2.org/configuration/options-api
 *
 * @FormElement("select2")
 */
class Select2 extends Select {

  use Select2Trait;

  /**
   * {@inheritdoc}
   */
  public function getInfo() {
    $info = parent::getInfo();
    $class = get_class($this);

    // Apply default form element properties.
    $info['#target_type'] = NULL;
    $info['#selection_handler'] = 'default';
    $info['#selection_settings'] = [];
    $info['#autocomplete'] = FALSE;
    $info['#autocreate'] = [];
    $info['#empty_value'] = '';
    $info['#cardinality'] = 0;
    $info['#pre_render'][] = [$class, 'preRenderAutocomplete'];
    $info['#pre_render'][] = [$class, 'preRenderOverwrites'];
    $info['#element_validate'][] = [$class, 'validateEntityAutocomplete'];
    $info['#select2'] = [];

    return $info;
  }

  /**
   * {@inheritdoc}
   */
  public static function valueCallback(&$element, $input, FormStateInterface $form_state) {
    // Potentially the #value is set directly, so it contains the 'target_id'
    // array structure instead of a string.
    if ($input !== FALSE && is_array($input)) {
      $input = array_map(function ($item) {
        return isset($item['target_id']) ? $item['target_id'] : $item;
      }, $input);
      return array_combine($input, $input);
    }

    return parent::valueCallback($element, $input, $form_state);
  }

  /**
   * Form element validation handler for entity_autocomplete elements.
   *
   * @param array $element
   *   The render element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state object.
   * @param array $complete_form
   *   The form array.
   */
  public static function validateEntityAutocomplete(array &$element, FormStateInterface $form_state, array &$complete_form) {
    if ($element['#target_type'] && !$element['#autocreate']) {
      $value_callable = isset($element['#autocomplete_options_callback']) ? $element['#autocomplete_options_callback'] : NULL;
      if (!$value_callable || !is_callable($value_callable)) {
        $value_callable = '\Drupal\select2\Element\Select2::getValidSelectedOptions';
      }

      $value = [];
      $input_values = call_user_func_array($value_callable, [
        $element,
        $form_state,
      ]);
      foreach ($input_values as $id => $input) {
        $value[] = [
          'target_id' => $id,
        ];
      }
      $form_state->setValueForElement($element, $value);
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function processSelect(&$element, FormStateInterface $form_state, &$complete_form) {
    // Fill the options, because in autocomplete we cleared them and for the
    // validation the at least selected options are needed.
    if ($element['#autocomplete']) {
      $value_callable = isset($element['#autocomplete_options_callback']) ? $element['#autocomplete_options_callback'] : NULL;
      if (!$value_callable || !is_callable($value_callable)) {
        $value_callable = '\Drupal\select2\Element\Select2::getValidSelectedOptions';
      }
      $element['#options'] = call_user_func_array($value_callable, [
        $element,
        $form_state,
      ]);
    }

    // We need to disable form validation, because with autocreation the options
    // could contain non existing references. We still have validation in the
    // entity reference field.
    if ($element['#autocreate'] && $element['#target_type']) {
      unset($element['#needs_validation']);

      // Add back auto_create values.
      $values = is_array($element['#value']) ? $element['#value'] : [$element['#value']];
      foreach ($values as $key => $value) {
        if (is_string($key) && substr($key, 0, 4) === "\$ID:") {
          // Set option and remove ID from label.
          $element['#options'][$key] = substr($value, 0, 4) === "\$ID:" ? substr($value, 4) : $value;
        }
        elseif (!$element['#multiple'] && substr($value, 0, 4) === "\$ID:") {
          $element['#options'][$value] = substr($value, 4);
        }
      }
    }

    if (!$element['#multiple'] && !isset($element['#options'][$element['#empty_value']])) {
      $empty_option = [$element['#empty_value'] => ''];
      $element['#options'] = $empty_option + $element['#options'];
    }

    // Set the type from select2 to select to get proper form validation.
    $element['#type'] = 'select';

    return $element;
  }

  /**
   * Get an array of currently selected options.
   *
   * @param array $element
   *   The render element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state object.
   *
   * @return array
   *   Key => entity ID, Value => entity label.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected static function getValidSelectedOptions(array $element, FormStateInterface $form_state) {
    $handler_settings = $element['#selection_settings'] + [
      'target_type' => $element['#target_type'],
      'handler' => $element['#selection_handler'],
    ];
    $value = is_array($element['#value']) ? $element['#value'] : [$element['#value']];
    return $value ? static::getValidReferenceableEntities($value, $handler_settings) : [];
  }

  /**
   * {@inheritdoc}
   */
  public static function preRenderSelect($element) {
    $element = parent::preRenderSelect($element);
    $required = isset($element['#states']['required']) ? TRUE : $element['#required'];
    $multiple = $element['#multiple'];

    if ($multiple) {
      $element['#attributes']['multiple'] = 'multiple';
      $element['#attributes']['name'] = $element['#name'] . '[]';
    }

    $current_language = \Drupal::languageManager()->getCurrentLanguage();
    $current_theme = \Drupal::theme()->getActiveTheme()->getName();
    $select2_theme_exists = \Drupal::service('library.discovery')->getLibraryByName($current_theme, 'select2.theme');

    // Placeholder should be taken from #placeholder property if it set.
    // Otherwise we can take it from '#empty_option' property.
    $placeholder_text = $required ? new TranslatableMarkup('- Select -') : new TranslatableMarkup('- None -');
    $placeholder = ['id' => '', 'text' => $placeholder_text];
    if (!empty($element['#empty_value'])) {
      $placeholder['id'] = $element['#empty_value'];
    }
    if (!empty($element['#placeholder'])) {
      $placeholder['text'] = $element['#placeholder'];
    }
    elseif (!empty($element['#empty_option'])) {
      $placeholder['text'] = $element['#empty_option'];
    }

    // Defining the select2 configuration.
    $settings = [
      'multiple' => $multiple,
      'placeholder' => $placeholder,
      // @todo Enable allowClear for multiple fields. https://github.com/select2/select2/issues/3335.
      'allowClear' => !$multiple && !$required,
      'dir' => $current_language->getDirection(),
      'language' => $current_language->getId(),
      'tags' => (bool) $element['#autocreate'],
      'theme' => $select2_theme_exists ? $current_theme : 'default',
      'maximumSelectionLength' => $multiple ? $element['#cardinality'] : 0,
      'tokenSeparators' => $element['#autocreate'] ? [','] : [],
      'selectOnClose' => $element['#autocomplete'],
      'width' => '100%',
    ];

    $element['#attributes']['class'][] = 'select2-widget';
    $element['#attributes']['data-select2-config'] = $settings;

    // Adding the select2 library.
    $element['#attached']['library'][] = 'select2/select2';
    $element['#attached']['library'][] = 'select2/select2.i18n.' . $current_language->getId();
    if ($select2_theme_exists) {
      $element['#attached']['library'][] = $current_theme . '/select2.theme';
    }
    return $element;
  }

  /**
   * Attach autocomplete behavior to the render element.
   */
  public static function preRenderAutocomplete($element) {
    if (!$element['#autocomplete']) {
      return $element;
    }

    $value_callable = isset($element['#autocomplete_route_callback']) ? $element['#autocomplete_route_callback'] : NULL;
    if (!$value_callable || !is_callable($value_callable)) {
      $value_callable = '\Drupal\select2\Element\Select2::setAutocompleteRouteParameters';
    }
    $element = call_user_func_array($value_callable, [&$element]);

    // Reduce options to the preselected ones and bring them in the correct
    // order.
    $options = OptGroup::flattenOptions($element['#options']);
    $values = isset($element['#value']) ? $element['#value'] : $element['#default_value'];
    $values = is_array($values) ? $values : [$values];
    $element['#options'] = [];
    foreach ($values as $value) {
      if (isset($options[$value])) {
        $element['#options'][$value] = $options[$value];
      }
    }

    /** @var \Drupal\Core\Access\AccessManagerInterface $access_manager */
    $access_manager = \Drupal::service('access_manager');
    $access = $access_manager->checkNamedRoute($element['#autocomplete_route_name'], $element['#autocomplete_route_parameters'], \Drupal::currentUser(), TRUE);

    if ($access && $access->isAllowed()) {
      $url = Url::fromRoute($element['#autocomplete_route_name'], $element['#autocomplete_route_parameters'])
        ->toString(TRUE);

      // Provide a data attribute for the JavaScript behavior to bind to.
      $element['#attributes']['data-select2-config'] += [
        'minimumInputLength' => 1,
        'ajax' => [
          'delay' => 250,
          'url' => $url->getGeneratedUrl(),
        ],
      ];
    }
    return $element;
  }

  /**
   * Sets the autocomplete route parameters.
   *
   * @param array $element
   *   The render element.
   *
   * @return array
   *   The render element with autocomplete route parameters.
   */
  protected static function setAutocompleteRouteParameters(array &$element) {
    $complete_form = [];
    $element = EntityAutocomplete::processEntityAutocomplete($element, new FormState(), $complete_form);
    $element['#autocomplete_route_name'] = 'select2.entity_autocomplete';
    return $element;
  }

  /**
   * Allows to modify the select2 settings.
   */
  public static function preRenderOverwrites($element) {
    if (!$element['#multiple']) {
      $empty_option = [$element['#empty_value'] => ''];
      $element['#options'] = $empty_option + $element['#options'];
    }

    // Allow to overwrite the default settings and set additional settings.
    foreach ($element["#select2"] as $key => $value) {
      $element['#attributes']['data-select2-config'][$key] = $value;
    }
    $element['#attributes']['data-select2-config'] = Json::encode($element['#attributes']['data-select2-config']);

    return $element;
  }

}
