<?php

namespace Drupal\block_field\Plugin\Field\FieldWidget;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormState;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\SubformState;

/**
 * Plugin implementation of the 'block_field' widget.
 *
 * @FieldWidget(
 *   id = "block_field_default",
 *   label = @Translation("Block field"),
 *   field_types = {
 *     "block_field"
 *   }
 * )
 */
class BlockFieldWidget extends WidgetBase {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'plugin_id' => '',
      'settings' => [],
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    /** @var \Drupal\block_field\BlockFieldItemInterface $item */
    $item =& $items[$delta];

    $field_name = $this->fieldDefinition->getName();
    $settings_id = implode('-', array_merge($element['#field_parents'], [$field_name, $delta, 'settings']));

    $plugin_ids = $this->fieldDefinition->getSetting('plugin_ids');

    $values = $form_state->getValues();
    $item->plugin_id = (isset($values[$field_name][$delta]['plugin_id'])) ? $values[$field_name][$delta]['plugin_id'] : $item->plugin_id;
    if (!empty($values[$field_name][$delta]['settings'])) {
      $item->settings = $values[$field_name][$delta]['settings'];
    }
    else {
      $item->settings = $item->settings ?: [];
    }

    $options = [];
    /** @var \Drupal\block_field\BlockFieldManagerInterface $block_field_manager */
    $block_field_manager = \Drupal::service('block_field.manager');
    $definitions = $block_field_manager->getBlockDefinitions();
    foreach ($definitions as $id => $definition) {
      // If allowed plugin ids are set then check that this block should be
      // included.
      if ($plugin_ids && !isset($plugin_ids[$id])) {
        // Remove the definition, so that we have an accurate list of allowed
        // blocks definitions.
        unset($definitions[$id]);
        continue;
      }
      $category = (string) $definition['category'];
      $options[$category][$id] = $definition['admin_label'];
    }

    // Make sure the plugin id is allowed, if not clear all settings.
    if ($item->plugin_id && !isset($definitions[$item->plugin_id])) {
      $item->plugin_id = '';
      $item->setting = [];
    }

    $element['plugin_id'] = [
      '#type' => 'select',
      '#title' => $this->t('Block'),
      '#options' => $options,
      '#empty_option' => $this->t('- None -'),
      '#default_value' => $item->plugin_id,
      '#required' => $element['#required'],
      '#ajax' => [
        'callback' => [$this, 'configurationForm'],
        'wrapper' => $settings_id,
      ],
    ];

    // Build configuration container.
    $element['settings'] = [
      '#type' => 'container',
      '#attributes' => ['id' => $settings_id],
      '#tree' => TRUE,
    ];

    // If block plugin exists get the block's configuration form.
    if ($block_instance = $item->getBlock()) {
      $element['settings'] += $block_instance->buildConfigurationForm([], $form_state);

      // Hide admin label (aka description).
      if (isset($element['settings']['admin_label'])) {
        $element['settings']['admin_label']['#access'] = FALSE;
      }

      // DEBUG:
      $element['#element_validate'] = [[$this, 'validate']];
    }

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  protected function formSingleElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $element = parent::formSingleElement($items, $delta, $element, $form, $form_state);
    // For single element set the plugin id title and description to use the
    // field's title and description.
    $element['plugin_id']['#title'] = $element['#title'];
    $element['plugin_id']['#title_display'] = $element['#title_display'];
    $element['plugin_id']['#description'] = $element['#description'];
    return $element;
  }

  /**
   * Ajax callback that return block configuration setting form.
   */
  public function configurationForm(array $form, FormStateInterface $form_state) {
    $trigger_element = $form_state->getTriggeringElement();
    $array_parents = $trigger_element['#array_parents'];
    $array_parents[(count($array_parents) - 1)] = 'settings';
    $settings_element = NestedArray::getValue($form, $array_parents);

    // Set the label #value to the default block instance's label.
    $plugin_id = $trigger_element['#value'];
    /** @var \Drupal\Core\Block\BlockManagerInterface $block_manager */
    $block_manager = \Drupal::service('plugin.manager.block');
    /** @var \Drupal\Core\Block\BlockPluginInterface $block_instance */
    if ($block_instance = $block_manager->createInstance($plugin_id)) {
      $settings_element['label']['#value'] = $block_instance->label();
    }

    return $settings_element;
  }

  /**
   * Form element validation handler.
   */
  public static function validate($element, FormStateInterface $form_state, $form) {
    /** @var \Drupal\Core\Block\BlockManagerInterface $block_manager */
    $block_manager = \Drupal::service('plugin.manager.block');

    $values = $form_state->getValues();
    $plugin_id = NestedArray::getValue($values, $element['plugin_id']['#parents']);

    if (!empty($plugin_id) && $block_manager->hasDefinition($plugin_id)) {
      // Clean up configuration settings.
      $settings = NestedArray::getValue($values, $element['settings']['#parents']);

      // Convert label display to FALSE instead of 0. This allow the label to be
      // hidden.
      if ($settings['label_display'] === 0) {
        $settings['label_display'] = FALSE;
      }

      // Execute block validate configuration.
      $block_instance = $block_manager->createInstance($plugin_id, $settings);
      $settings = (new FormState())->setValues($settings);
      $block_instance->validateConfigurationForm($form, $settings);

      NestedArray::setValue($values, $element['settings']['#parents'], $settings->getValues());
      $form_state->setValues($values);
    }
    else {
      // Clear all configuration settings.
      NestedArray::setValue($values, $element['settings']['#parents'], []);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function massageFormValues(array $values, array $form, FormStateInterface $form_state) {
    // @todo: Inject the block manager as proper dependency.
    /** @var \Drupal\Core\Block\BlockManagerInterface $block_manager */
    $block_manager = \Drupal::service('plugin.manager.block');
    $field_name = $this->fieldDefinition->getName();

    // Some blocks clean the processed values in form state. However, entity
    // forms extract the form values twice during submission. For the second
    // submission to work as well, we need to prevent the removal of the form
    // values during the first submission.
    $form_state = clone $form_state;

    foreach ($values as $delta => &$value) {
      // Execute block submit configuration in order to transform the form
      // values into block configuration.
      if (!empty($value['plugin_id']) && !empty($value['settings']) && $block = $block_manager->createInstance($value['plugin_id'])) {
        $elements = &$form[$field_name]['widget'][$delta]['settings'];
        $subform_state = SubformState::createForSubform($elements, $form_state->getCompleteForm(), $form_state);
        $block->submitConfigurationForm($elements, $subform_state);
        $value['settings'] = $block->getConfiguration();
      }
    }
    return $values;
  }

}
