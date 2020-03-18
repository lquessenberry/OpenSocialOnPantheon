<?php

namespace Drupal\image_effects\Tests\Update;

use Drupal\system\Tests\Update\UpdatePathTestBase;

/**
 * Tests the upgrade path for 'Text overlay' effect.
 *
 * @see image_effects_post_update_text_overlay_maximum_chars()
 *
 * @group Image Effects
 */
class TextOverlayUpdateTest extends UpdatePathTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['image_effects'];

  /**
   * {@inheritdoc}
   */
  protected function setDatabaseDumpFiles() {
    $this->databaseDumpFiles = [
      __DIR__ . '/../../../tests/fixtures/update/d_820_ie_810a2.php.gz',
    ];
  }

  /**
   * Tests that 'Text overlay' effects are updated properly.
   */
  public function testTextOverlayUpdate() {
    // Test that Text overlay effect does not have parameters introduced after
    // 8.x-1.0-alpha2.
    $effect_data = $this->config('image.style.test_text_overlay')->get('effects.8287f632-3b1f-4a6f-926f-119550cc0948.data');
    $this->assertFalse(array_key_exists('maximum_chars', $effect_data['text']));
    $this->assertFalse(array_key_exists('excess_chars_text', $effect_data['text']));
    $this->assertFalse(array_key_exists('strip_tags', $effect_data['text']));
    $this->assertFalse(array_key_exists('decode_entities', $effect_data['text']));

    // Run updates.
    $this->runUpdates();

    // Test that Text overlay effect has parameters introduced after
    // 8.x-1.0-alpha2, with the expected defaults.
    $effect_data = $this->config('image.style.test_text_overlay')->get('effects.8287f632-3b1f-4a6f-926f-119550cc0948.data');
    $this->assertTrue(array_key_exists('maximum_chars', $effect_data['text']));
    $this->assertNull($effect_data['text']['maximum_chars']);
    $this->assertTrue(array_key_exists('excess_chars_text', $effect_data['text']));
    $this->assertEqual('…', $effect_data['text']['excess_chars_text']);
    $this->assertTrue(array_key_exists('strip_tags', $effect_data['text']));
    $this->assertTrue($effect_data['text']['strip_tags']);
    $this->assertTrue(array_key_exists('decode_entities', $effect_data['text']));
    $this->assertTrue($effect_data['text']['decode_entities']);
  }

}
