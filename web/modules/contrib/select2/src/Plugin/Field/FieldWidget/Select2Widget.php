<?php

namespace Drupal\select2\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\Plugin\Field\FieldWidget\OptionsSelectWidget;
use Drupal\Core\Form\FormStateInterface;

/**
 * Plugin implementation of the 'select2' widget.
 *
 * @FieldWidget(
 *   id = "select2",
 *   label = @Translation("Select2"),
 *   field_types = {
 *     "list_integer",
 *     "list_float",
 *     "list_string"
 *   },
 *   multiple_values = TRUE
 * )
 */
class Select2Widget extends OptionsSelectWidget {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'width' => '100%',
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $element['width'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Field width'),
      '#default_value' => $this->getSetting('width'),
      '#description' => $this->t("Define a width for the select2 field. It can be either 'element', 'computedstyle', 'style', 'resolve' or any possible CSS value. E.g. 500px, 50%, 200em. See the <a href='https://select2.org/appearance#container-width'>select2 documentation</a> for further explanations."),
      '#required' => TRUE,
      '#size' => '12',
      '#pattern' => "([0-9]*\.[0-9]+|[0-9]+)(cm|mm|in|px|pt|pc|em|ex|ch|rem|vm|vh|vmin|vmax|%)|element|computedstyle|style|resolve|auto|initial|inherit",
    ];
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = [];
    $summary[] = $this->t('Field width: @width', ['@width' => $this->getSetting('width')]);
    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $element = parent::formElement($items, $delta, $element, $form, $form_state);
    $element['#type'] = 'select2';
    $element['#cardinality'] = $this->fieldDefinition->getFieldStorageDefinition()->getCardinality();
    $element['#select2'] = [
      'width' => $this->getSetting('width'),
    ];

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  protected function getEmptyLabel() {}

  /**
   * {@inheritdoc}
   */
  public static function validateElement(array $element, FormStateInterface $form_state) {
    if ($element['#required'] && $element['#value'] == '') {
      $form_state->setError($element, t('@name field is required.', ['@name' => $element['#title']]));
    }

    // Massage submitted form values.
    // Drupal\Core\Field\WidgetBase::submit() expects values as
    // an array of values keyed by delta first, then by column, while our
    // widgets return the opposite.
    if (is_array($element['#value'])) {
      $values = array_values($element['#value']);
    }
    else {
      $values = [$element['#value']];
    }

    // Filter out the '' option. Use a strict comparison, because
    // 0 == 'any string'.
    $index = array_search('', $values, TRUE);
    if ($index !== FALSE) {
      unset($values[$index]);
    }

    $items = static::prepareFieldValues($values, $element);
    $form_state->setValueForElement($element, $items);
  }

  /**
   * Set's the values to the correct column key.
   *
   * @param array $values
   *   The input values.
   * @param array $element
   *   The render element.
   *
   * @return array
   *   Values with the correct keys.
   */
  protected static function prepareFieldValues(array $values, array $element) {
    // Transpose selections from field => delta to delta => field.
    $items = [];
    foreach ($values as $value) {
      $items[] = [$element['#key_column'] => $value];
    }
    return $items;
  }

}
