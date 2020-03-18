<?php

namespace Drupal\image_effects\Tests\Update;

use Drupal\system\Tests\Update\UpdatePathTestBase;

/**
 * Tests the upgrade path for 'watermark' effect.
 *
 * @see image_effects_post_update_watermark_alpha6()
 *
 * @group Image Effects
 */
class WatermarkUpdateTest extends UpdatePathTestBase {

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
      __DIR__ . '/../../../tests/fixtures/update/test_watermark.php',
    ];
  }

  /**
   * Tests that 'Watermark' effects are updated properly.
   */
  public function testWatermarkUpdate() {
    // Test that Watermark effect has parameters as valid before
    // 8.x-1.0-alpha6.
    $effect_data = $this->config('image.style.test_watermark_scale')->get('effects.3d493386-5251-4d45-b395-2e036f7203c0.data');
    $this->assertFalse(array_key_exists('watermark_width', $effect_data));
    $this->assertFalse(array_key_exists('watermark_height', $effect_data));
    $this->assertIdentical(10, $effect_data['x_offset']);
    $this->assertIdentical(10, $effect_data['y_offset']);
    $this->assertIdentical(20, $effect_data['watermark_scale']);

    $effect_data = $this->config('image.style.test_watermark_no_scale')->get('effects.253dcaa0-27f0-49ef-9d5f-4bda9bf78ff7.data');
    $this->assertFalse(array_key_exists('watermark_width', $effect_data));
    $this->assertFalse(array_key_exists('watermark_height', $effect_data));
    $this->assertIdentical(10, $effect_data['x_offset']);
    $this->assertIdentical(10, $effect_data['y_offset']);
    $this->assertNull($effect_data['watermark_scale']);

    // Run updates.
    $this->runUpdates();

    // Test that Watermark effect has parameters as introduced in
    // 8.x-1.0-alpha6.
    $effect_data = $this->config('image.style.test_watermark_scale')->get('effects.3d493386-5251-4d45-b395-2e036f7203c0.data');
    $this->assertTrue(array_key_exists('watermark_width', $effect_data));
    $this->assertTrue(array_key_exists('watermark_height', $effect_data));
    $this->assertIdentical('10', $effect_data['x_offset']);
    $this->assertIdentical('10', $effect_data['y_offset']);
    $this->assertIdentical('20%', $effect_data['watermark_width']);
    $this->assertFalse(array_key_exists('watermark_scale', $effect_data));

    $effect_data = $this->config('image.style.test_watermark_no_scale')->get('effects.253dcaa0-27f0-49ef-9d5f-4bda9bf78ff7.data');
    $this->assertTrue(array_key_exists('watermark_width', $effect_data));
    $this->assertTrue(array_key_exists('watermark_height', $effect_data));
    $this->assertIdentical('10', $effect_data['x_offset']);
    $this->assertIdentical('10', $effect_data['y_offset']);
    $this->assertNull($effect_data['watermark_width']);
    $this->assertFalse(array_key_exists('watermark_scale', $effect_data));
  }

}
