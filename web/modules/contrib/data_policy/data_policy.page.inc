<?php

/**
 * @file
 * Contains data_policy.page.inc.
 *
 * Page callback for Data policy entities.
 */

use Drupal\Core\Render\Element;

/**
 * Prepares variables for Data policy templates.
 *
 * Default template: data_policy.html.twig.
 *
 * @param array $variables
 *   An associative array containing:
 *   - elements: An associative array containing the user information and any
 *   - attributes: HTML attributes for the containing element.
 */
function template_preprocess_data_policy(array &$variables) {
  // Helpful $content variable for templates.
  foreach (Element::children($variables['elements']) as $key) {
    $variables['content'][$key] = $variables['elements'][$key];
  }
}
