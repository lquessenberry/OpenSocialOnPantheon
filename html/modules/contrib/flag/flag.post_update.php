<?php

/**
 * @file
 * Post update functions for Flag.
 */

/**
 * Implements hook_post_update_NAME().
 *
 * Updates the dependency information in views that depend on flag.
 */
function flag_post_update_flag_relationship_dependencies(&$sandbox) {
  // Load all views.
  $views = \Drupal::entityTypeManager()->getStorage('view')->loadMultiple();

  /* @var \Drupal\views\Entity\View[] $views */
  foreach ($views as $view) {
    // Views that use the flag_relationship plugin will depend on the Flag
    // module already.
    if (in_array('flag', $view->getDependencies()['module'], TRUE)) {
      $old_dependencies = $view->getDependencies();
      // If we've changed the dependencies, for example, to add a dependency on
      // the flag used in the relationship, then re-save the view.
      if ($old_dependencies !== $view->calculateDependencies()->getDependencies()) {
        $view->save();
      }
    }
  }
}
