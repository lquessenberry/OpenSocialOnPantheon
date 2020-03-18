<?php

/**
 * @file
 * Test fixture for image style with watermark effect.
 */

use Drupal\Core\Database\Database;
use Drupal\Core\Serialization\Yaml;

$connection = Database::getConnection();

foreach (['image.style.test_watermark_scale', 'image.style.test_watermark_no_scale'] as $style) {
  $connection->insert('config')
    ->fields([
      'collection' => '',
      'name' => $style,
      'data' => serialize(Yaml::decode(file_get_contents('modules/contrib/image_effects/tests/fixtures/update/' . $style . '.yml'))),
    ])
    ->execute();
}
