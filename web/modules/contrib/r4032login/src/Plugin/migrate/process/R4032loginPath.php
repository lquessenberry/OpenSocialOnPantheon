<?php

namespace Drupal\r4032login\Plugin\migrate\process;

use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;

/**
 * Maps D7 r4032login path settings to D9.
 *
 * @MigrateProcessPlugin(
 *   id = "r4032login_path"
 * )
 */
class R4032loginPath extends ProcessPluginBase {

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    // Adds a leading slash to all the paths.
    $all_paths = '';
    foreach (explode("\r\n", $value) as $path) {
      $trimmed_path = trim($path);
      if ($trimmed_path) {
        $all_paths = $all_paths . '/' . $trimmed_path . "\n";
      }
    }
    return rtrim($all_paths);
  }

}
