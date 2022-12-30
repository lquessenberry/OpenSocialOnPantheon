<?php

namespace Drupal\bootstrap\Plugin\Alter;

use Drupal\bootstrap\Plugin\PluginBase;

/**
 * Implements hook_library_info_alter().
 *
 * @ingroup plugins_alter
 *
 * @BootstrapAlter("library_info")
 */
class LibraryInfo extends PluginBase implements AlterInterface {

  /**
   * {@inheritdoc}
   */
  public function alter(&$libraries, &$extension = NULL, &$context2 = NULL) {
    $livereload = $this->theme->livereloadUrl();

    // Disable preprocess on all CSS/JS if "livereload" is enabled.
    if ($livereload) {
      $this->processLibrary($libraries, function (&$info, &$key, $type) {
        if ($type === 'css' || $type === 'js') {
          $info['preprocess'] = FALSE;
        }
      });
    }

    if ($extension === 'bootstrap') {
      // Alter the "livereload.js" placeholder with the correct URL.
      if ($livereload) {
        $libraries['livereload']['js'][$livereload] = $libraries['livereload']['js']['livereload.js'];
        unset($libraries['livereload']['js']['livereload.js']);
      }

      // Alter the framework library based on currently set CDN Provider.
      $this->theme->getCdnProvider()->alterFrameworkLibrary($libraries['framework']);

      // Add back deprecated library dependencies that are only available in D8.
      if (((int) substr(\Drupal::VERSION, 0, 1)) < 9) {
        $libraries['drupal.vertical-tabs']['dependencies'][] = 'core/matchmedia';
      }
    }
    // Core replacements.
    elseif ($extension === 'core') {
      // Replace core dialog/jQuery UI implementations with Bootstrap Modals.
      if ($this->theme->getSetting('modal_enabled')) {
        // Replace dependencies if using bridge so jQuery UI is not loaded
        // and remove dialog.jquery-ui.js since the dialog widget isn't loaded.
        if ($this->theme->getSetting('modal_jquery_ui_bridge')) {
          // Remove core's jquery.ui.dialog dependency.
          $key = array_search('core/jquery.ui.dialog', $libraries['drupal.dialog']['dependencies']);
          if ($key !== FALSE) {
            unset($libraries['drupal.dialog']['dependencies'][$key]);
          }

          // Remove core's dialog.jquery-ui.js.
          unset($libraries['drupal.dialog']['js']['misc/dialog/dialog.jquery-ui.js']);

          // Add the Modal jQuery UI Bridge.
          $libraries['drupal.dialog']['dependencies'][] = 'bootstrap/dialog';
          $libraries['drupal.dialog']['dependencies'][] = 'bootstrap/modal.jquery.ui.bridge';
        }
        // Otherwise, just append the modal.
        else {
          $libraries['drupal.dialog']['dependencies'][] = 'bootstrap/modal';
          $libraries['drupal.dialog']['dependencies'][] = 'bootstrap/dialog';
        }
      }
    }
  }

  /**
   * Processes library definitions.
   *
   * @param array $libraries
   *   The libraries array, passed by reference.
   * @param callable $callback
   *   The callback to perform processing on the library.
   */
  public function processLibrary(array &$libraries, callable $callback) {
    foreach ($libraries as &$library) {
      foreach ($library as $type => $definition) {
        if (is_array($definition)) {
          $modified = [];
          // CSS needs special handling since it contains grouping.
          if ($type === 'css') {
            foreach ($definition as $group => $files) {
              foreach ($files as $key => $info) {
                call_user_func_array($callback, [&$info, &$key, $type]);
                $modified[$group][$key] = $info;
              }
            }
          }
          else {
            foreach ($definition as $key => $info) {
              call_user_func_array($callback, [&$info, &$key, $type]);
              $modified[$key] = $info;
            }
          }
          $library[$type] = $modified;
        }
      }
    }
  }

}
