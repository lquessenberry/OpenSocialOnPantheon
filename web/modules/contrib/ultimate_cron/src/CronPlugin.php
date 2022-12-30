<?php

namespace Drupal\ultimate_cron;
use Drupal\Component\Plugin\ConfigurableInterface;
use Drupal\Component\Plugin\DependentPluginInterface;
use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\PluginBase;
use Drupal\Core\Plugin\PluginFormInterface;

/**
 * This is the base class for all Ultimate Cron plugins.
 *
 * This class handles all the load/save settings for a plugin as well as the
 * forms, etc.
 */
class CronPlugin extends PluginBase implements PluginInspectionInterface, ConfigurableInterface, DependentPluginInterface, PluginFormInterface {
  static public $multiple = FALSE;
  static public $instances = array();
  public $weight = 0;
  static public $globalOptions = array();

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->setConfiguration($configuration);
  }

  /**
   * Returns a list of plugin types.
   *
   * @return array
   */
  public static function getPluginTypes() {
    return array(
      'scheduler' => t('Scheduler'),
      'launcher' => t('Launcher'),
      'logger' => t('Logger')
    );
  }


  /**
   * {@inheritdoc}
   */
  public function getConfiguration() {
    return $this->configuration;
  }

  /**
   * {@inheritdoc}
   */
  public function setConfiguration(array $configuration) {
    $this->configuration = array_merge(
      $this->defaultConfiguration(),
      $configuration
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return array();
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies() {
    return array();
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {

  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    return array();
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {

  }

  /**
   * Get global plugin option.
   *
   * @param string $name
   *   Name of global plugin option to get.
   *
   * @return mixed
   *   Value of option if any, NULL if not found.
   */
  static public function getGlobalOption($name) {
    return isset(static::$globalOptions[$name]) ? static::$globalOptions[$name] : NULL;
  }

  /**
   * Get all global plugin options.
   *
   * @return array
   *   All options currently set, keyed by name.
   */
  static public function getGlobalOptions() {
    return static::$globalOptions;
  }

  /**
   * Set global plugin option.
   *
   * @param string $name
   *   Name of global plugin option to get.
   * @param string $value
   *   The value to give it.
   */
  static public function setGlobalOption($name, $value) {
    static::$globalOptions[$name] = $value;
  }

  /**
   * Get label for a specific setting.
   */
  public function settingsLabel($name, $value) {
    if (is_array($value)) {
      return implode(', ', $value);
    }
    else {
      return $value;
    }
  }

  /**
   * Default plugin valid for all jobs.
   */
  public function isValid($job = NULL) {
    return TRUE;
  }

  /**
   * Modified version drupal_array_get_nested_value().
   *
   * Removes the specified parents leaf from the array.
   *
   * @param array $array
   *   Nested associative array.
   * @param array $parents
   *   Array of key names forming a "path" where the leaf will be removed
   *   from $array.
   */
  public function drupal_array_remove_nested_value(array &$array, array $parents) {
    $ref = & $array;
    $last_parent = array_pop($parents);
    foreach ($parents as $parent) {
      if (is_array($ref) && array_key_exists($parent, $ref)) {
        $ref = & $ref[$parent];
      }
      else {
        return;
      }
    }
    unset($ref[$last_parent]);
  }

  /**
   * Clean form of empty fallback values.
   */
  public function cleanForm($elements, &$values, $parents) {
    if (empty($elements)) {
      return;
    }

    foreach (element_children($elements) as $child) {
      if (empty($child) || empty($elements[$child]) || is_numeric($child)) {
        continue;
      }
      // Process children.
      $this->cleanForm($elements[$child], $values, $parents);

      // Determine relative parents.
      $rel_parents = array_diff($elements[$child]['#parents'], $parents);
      $key_exists = NULL;
      $value = drupal_array_get_nested_value($values, $rel_parents, $key_exists);

      // Unset when applicable.
      if (!empty($elements[$child]['#markup'])) {
        static::drupal_array_remove_nested_value($values, $rel_parents);
      }
      elseif (
        $key_exists &&
        empty($value) &&
        !empty($elements[$child]['#fallback']) &&
        $value !== '0'
      ) {
        static::drupal_array_remove_nested_value($values, $rel_parents);
      }
    }
  }

  /**
   * Process fallback form parameters.
   *
   * @param array $elements
   *   Elements to process.
   * @param array $defaults
   *   Default values to add to description.
   * @param bool $remove_non_fallbacks
   *   If TRUE, non fallback elements will be removed.
   */
  public function fallbackalize(&$elements, &$values, $defaults, $remove_non_fallbacks = FALSE) {
    if (empty($elements)) {
      return;
    }
    foreach (element_children($elements) as $child) {
      $element = & $elements[$child];
      if (empty($element['#tree'])) {
        $param_values = & $values;
        $param_defaults = & $defaults;
      }
      else {
        $param_values = & $values[$child];
        $param_defaults = & $defaults[$child];
      }
      $this->fallbackalize($element, $param_values, $param_defaults, $remove_non_fallbacks);

      if (empty($element['#type']) || $element['#type'] == 'fieldset') {
        continue;
      }

      if (!empty($element['#fallback'])) {
        if (!$remove_non_fallbacks) {
          if ($element['#type'] == 'radios') {
            $label = $this->settingsLabel($child, $defaults[$child]);
            $element['#options'] = array(
              '' => t('Default (@default)', array('@default' => $label)),
            ) + $element['#options'];
          }
          elseif ($element['#type'] == 'select' && empty($element['#multiple'])) {
            $label = $this->settingsLabel($child, $defaults[$child]);
            $element['#options'] = array(
              '' => t('Default (@default)', array('@default' => $label)),
            ) + $element['#options'];
          }
          elseif ($defaults[$child] !== '') {
            $element['#description'] .= ' ' . t('(Blank = @default).', array('@default' => $this->settingsLabel($child, $defaults[$child])));
          }
          unset($element['#required']);
        }
      }
      elseif (!empty($element['#type']) && $remove_non_fallbacks) {
        unset($elements[$child]);
      }
      elseif (!isset($element['#default_value']) || $element['#default_value'] === '') {
        $empty = $element['#type'] == 'checkbox' ? FALSE : '';
        $values[$child] = !empty($defaults[$child]) ? $defaults[$child] : $empty;
        $element['#default_value'] = $values[$child];
      }
    }
  }

}
