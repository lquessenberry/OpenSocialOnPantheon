<?php

namespace Drupal\ultimate_cron;

class CronPluginMultiple extends CronPlugin {
  static public $multiple = TRUE;

  /**
   * Default settings form.
   */
  static public function defaultSettingsForm(&$form, &$form_state, $plugin_info) {
    $plugin_type = $plugin_info['type'];
    foreach (ultimate_cron_plugin_load_all($plugin_type) as $name => $plugin) {
      if ($plugin->isValid()) {
        $plugins[] = l($plugin->title, "admin/config/system/cron/$plugin_type/$name");
      }
    }
    $form['available'] = array(
      '#markup' => theme('item_list', array(
        'title' => $plugin_info['defaults']['static']['title plural proper'] . ' available',
        'items' => $plugins
      ))
    );
  }

  /**
   * Job settings form.
   */
  static public function jobSettingsForm(&$form, &$form_state, $plugin_type, $job) {
    // Check valid plugins.
    $plugins = ultimate_cron_plugin_load_all($plugin_type);
    foreach ($plugins as $name => $plugin) {
      if (!$plugin->isValid($job)) {
        unset($plugins[$name]);
      }
    }

    // No plugins = no settings = no vertical tabs for you mister!
    if (empty($plugins)) {
      return;
    }

    $weight = 10;
    $form_state['default_values']['settings'][$plugin_type] = array();
    $form['settings'][$plugin_type]['#tree'] = TRUE;
    foreach ($plugins as $name => $plugin) {
      $form_state['default_values']['settings'][$plugin_type][$name] = array();
      if (empty($form_state['values']['settings'][$plugin_type][$name])) {
        $form_state['values']['settings'][$plugin_type][$name] = array();
      }
      $form['settings'][$plugin_type][$name] = array(
        '#title' => $plugin->title,
        '#group' => 'settings_tabs',
        '#type' => 'fieldset',
        '#tree' => TRUE,
        '#visible' => TRUE,
        '#collapsible' => TRUE,
        '#collapsed' => TRUE,
        '#weight' => $weight++,
      );

      $defaults = $plugin->getDefaultSettings($job);

      $form_state['default_values']['settings'][$plugin_type][$name] += $defaults;
      $form_state['values']['settings'][$plugin_type][$name] += ultimate_cron_blank_values($defaults);

      $plugin->settingsForm($form, $form_state, $job);
      if (empty($form['settings'][$plugin_type][$name]['no_settings'])) {
        $plugin->fallbackalize(
          $form['settings'][$plugin_type][$name],
          $form_state['values']['settings'][$plugin_type][$name],
          $form_state['default_values']['settings'][$plugin_type][$name],
          FALSE
        );
      }
      else {
        unset($form['settings'][$plugin_type][$name]);
      }
    }
  }

  /**
   * Job settings form validate handler.
   */
  static public function jobSettingsFormValidate($form, &$form_state, $plugin_type, $job = NULL) {
    $plugins = ultimate_cron_plugin_load_all($plugin_type);
    foreach ($plugins as $plugin) {
      if ($plugin->isValid($job)) {
        $plugin->settingsFormValidate($form, $form_state, $job);
      }
    }
  }

  /**
   * Job settings form submit handler.
   */
  static public function jobSettingsFormSubmit($form, &$form_state, $plugin_type, $job = NULL) {
    $plugins = ultimate_cron_plugin_load_all($plugin_type);
    foreach ($plugins as $name => $plugin) {
      if ($plugin->isValid($job)) {
        $plugin->settingsFormSubmit($form, $form_state, $job);

        // Weed out blank values that have fallbacks.
        $elements = & $form['settings'][$plugin_type][$name];
        $values = & $form_state['values']['settings'][$plugin_type][$name];
        $plugin->cleanForm($elements, $values, array(
          'settings',
          $plugin_type,
          $name
        ));
      }
      else {
        unset($form_state['values']['settings'][$plugin_type][$name]);
      }
    }
  }
}
