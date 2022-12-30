<?php

namespace Drupal\bootstrap\Plugin\Setting;

use Drupal\bootstrap\Bootstrap;
use Drupal\bootstrap\Plugin\PluginBase;
use Drupal\bootstrap\Utility\Element;
use Drupal\Core\Access\AccessResultAllowed;
use Drupal\Core\Access\AccessResultForbidden;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Base class for a setting.
 *
 * @ingroup plugins_setting
 */
class SettingBase extends PluginBase implements SettingInterface {

  public static $autoUserInterface = TRUE;

  /**
   * {@inheritdoc}
   */
  public function access() {
    // Hide the setting if is been deprecated.
    if ($this instanceof DeprecatedSettingInterface) {
      return AccessResultForbidden::forbidden();
    }
    return AccessResultAllowed::allowed();
  }

  /**
   * {@inheritdoc}
   */
  public function alterForm(array &$form, FormStateInterface $form_state, $form_id = NULL) {
    $this->alterFormElement(Element::create($form, $form_state), $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function alterFormElement(Element $form, FormStateInterface $form_state, $form_id = NULL) {
    $this->getSettingElement($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function autoCreateFormElement() {
    return !($this instanceof DeprecatedSettingInterface);
  }

  /**
   * {@inheritdoc}
   */
  public function drupalSettings() {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags() {
    return ['rendered'];
  }

  /**
   * Retrieves all the form properties from the setting definition.
   *
   * @return array
   *   The form properties.
   */
  public function getElementProperties() {
    $properties = $this->getPluginDefinition();
    $ignore_keys = [
      'class',
      'defaultValue',
      'definition',
      'groups',
      'id',
      'provider',
      'see',
    ];
    foreach ($properties as $name => $value) {
      if (in_array($name, $ignore_keys)) {
        unset($properties[$name]);
      }
    }
    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultValue() {
    return isset($this->pluginDefinition['defaultValue']) ? $this->pluginDefinition['defaultValue'] : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return isset($this->pluginDefinition['description']) ? $this->pluginDefinition['description'] : NULL;
  }

  /**
   * {@inheritdoc}
   *
   * @deprecated Will be removed in a future release. Use \Drupal\bootstrap\Plugin\Setting\SettingInterface::getGroupElement
   */
  public function getGroup(array &$form, FormStateInterface $form_state) {
    Bootstrap::deprecated();
    return $this->getGroupElement(Element::create($form, $form_state), $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function getGroupElement(Element $form, FormStateInterface $form_state) {
    $groups = $this->getGroups();
    $group = $form;
    $first = TRUE;
    foreach ($groups as $key => $title) {
      if (!isset($group->$key)) {
        if ($title) {
          $group->$key = ['#type' => 'details', '#title' => $title];
        }
        else {
          $group->$key = ['#type' => 'container'];
        }
        $group = Element::create($group->$key->getArray(), $form_state);
        if ($first) {
          $group->setProperty('group', 'bootstrap');
        }
        else {
          $group->setProperty('open', FALSE);
        }
      }
      else {
        $group = Element::create($group->$key->getArray(), $form_state);
      }
      $first = FALSE;
    }
    return $group;
  }

  /**
   * {@inheritdoc}
   */
  public function getGroups() {
    return !empty($this->pluginDefinition['groups']) ? $this->pluginDefinition['groups'] : [];
  }

  /**
   * {@inheritdoc}
   *
   * @deprecated Will be removed in a future release. Use \Drupal\bootstrap\Plugin\Setting\SettingInterface::getSettingElement
   */
  public function getElement(array &$form, FormStateInterface $form_state) {
    Bootstrap::deprecated();
    return $this->getSettingElement(Element::create($form, $form_state), $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function getOptions() {
    return isset($this->pluginDefinition['options']) ? (array) $this->pluginDefinition['options'] : [];
  }

  /**
   * {@inheritdoc}
   */
  public function getSettingElement(Element $form, FormStateInterface $form_state) {
    // Construct the group elements.
    $group = $this->getGroupElement($form, $form_state);
    $plugin_id = $this->getPluginId();
    if (!isset($group->$plugin_id)) {
      // Set properties from the plugin definition.
      foreach ($this->getElementProperties() as $name => $value) {
        $group->$plugin_id->setProperty($name, $value);
      }

      // Get the default value.
      $default_value = $this->getSettingValue($form_state);

      // Convert value from an array into a newline separated value.
      // @todo Remove once settings have proper config schemas in place.
      // @see https://www.drupal.org/project/bootstrap/issues/2883714
      if ($group->$plugin_id->getProperty('type') === 'textarea' && is_array($default_value)) {
        $default_value = implode("\n", $default_value);
      }

      // Set default value from the stored form state value or theme setting.
      $group->$plugin_id->setProperty('default_value', $default_value);

      // Append additional "see" link references to the description.
      $description = (string) $group->$plugin_id->getProperty('description') ?: '';
      $links = [];
      foreach ($this->pluginDefinition['see'] as $url => $title) {
        $link = Element::createStandalone([
          '#type' => 'link',
          '#url' => Url::fromUri($url),
          '#title' => $title,
          '#attributes' => [
            'target' => '_blank',
          ],
        ], $form_state);
        $links[] = (string) $link->renderPlain();
      }
      if (!empty($links)) {
        $description .= '<br>';
        $description .= $this->t('See also:');
        $description .= ' ' . implode(', ', $links);
        $group->$plugin_id->setProperty('description', $description);
      }
    }

    // Set accessibility.
    $group->$plugin_id->access($this->access());

    return $group->$plugin_id;
  }

  /**
   * Retrieves the setting value used to populate the form.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form state.
   *
   * @return mixed
   *   The setting value.
   */
  protected function getSettingValue(FormStateInterface $form_state) {
    $plugin_id = $this->getPluginId();
    return $form_state->getValue($plugin_id, $this->theme->getSetting($plugin_id));
  }

  /**
   * {@inheritdoc}
   */
  public function getTitle() {
    return !empty($this->pluginDefinition['title']) ? $this->pluginDefinition['title'] : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function processDeprecatedValues(array $values, array $deprecated) {
    // Most deprecated settings will be a 1:1 map. Anything more complex than
    // this should be handled by the newer replacement setting itself.
    return !empty($values) ? reset($values) : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public static function submitForm(array &$form, FormStateInterface $form_state) {
    static::submitFormElement(Element::create($form, $form_state), $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public static function submitFormElement(Element $form, FormStateInterface $form_state) {}

  /**
   * {@inheritdoc}
   */
  public static function validateForm(array &$form, FormStateInterface $form_state) {
    static::validateFormElement(Element::create($form, $form_state), $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public static function validateFormElement(Element $form, FormStateInterface $form_state) {}

}
