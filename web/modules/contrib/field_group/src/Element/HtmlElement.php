<?php

namespace Drupal\field_group\Element;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element\RenderElement;

/**
 * Provides a render element for a html element.
 *
 * @FormElement("field_group_html_element")
 */
class HtmlElement extends RenderElement {

  /**
   * {@inheritdoc}
   */
  public function getInfo() {
    $class = get_class($this);

    return [
      '#process' => [
        [$class, 'processGroup'],
        [$class, 'processHtmlElement'],
      ],
      '#pre_render' => [
        [$class, 'preRenderGroup'],
      ],
      '#theme_wrappers' => ['field_group_html_element'],
    ];
  }

  /**
   * Process a html element.
   *
   * @param array $element
   *   An associative array containing the properties and children of the
   *   details element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return array
   *   The processed element.
   */
  public static function processHtmlElement(array &$element, FormStateInterface $form_state) {

    // If an effect is set, we need to load extra js.
    if (!empty($element['#effect']) && $element['#effect'] !== 'none') {

      $element['#attached']['library'][] = 'field_group/formatter.html_element';
      $element['#attached']['library'][] = 'field_group/core';

      // Add the required classes for the js.
      $element['#attributes']['class'][] = 'field-group-html-element';
      $element['#attributes']['class'][] = 'fieldgroup-collapsible';
      $element['#attributes']['class'][] = 'effect-' . $element['#effect'];
      if (!empty($element['#speed'])) {
        $element['#attributes']['class'][] = 'speed-' . $element['#speed'];
      }

      // Add jquery ui effects library for the blind effect.
      if ($element['#effect'] == 'blind') {
        $element['#attached']['library'][] = 'core/jquery.ui.effects.blind';
      }

    }

    return $element;
  }

}
