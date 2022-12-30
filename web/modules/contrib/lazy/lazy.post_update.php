<?php

/**
 * @file
 * Lazy module post updates.
 */

/**
 * Update lazy module configuration for the new "preferNative" option.
 */
function lazy_post_update_add_prefernative_option() {
  $config = \Drupal::configFactory()->getEditable('lazy.settings');
  // If it's already set use that value, otherwise set to false.
  $preferNative = (bool) (($config->get('preferNative') !== NULL) ? $config->get('preferNative') : FALSE);
  $config->set('preferNative', $preferNative)->save(TRUE);

  return t('The new "preferNative" option is added, and set to %status. (Default: <em>false</em>)', [
    '%status' => $preferNative ? 'enabled' : 'disabled',
  ]);
}

/**
 * Update lazy module configuration for the new "cssEffect" option.
 */
function lazy_post_update_add_csseffect_option() {
  $config = \Drupal::configFactory()->getEditable('lazy.settings');
  // If it's already set use that value, otherwise set to false.
  $cssEffect = (bool) ($config->get('cssEffect') ?? FALSE);
  $config->set('preferNative', $cssEffect)->save(TRUE);

  return t('The new "cssEffect" option is added, and set to %status. (Default: <em>false</em>)', [
    '%status' => $cssEffect ? 'enabled' : 'disabled',
  ]);
}
