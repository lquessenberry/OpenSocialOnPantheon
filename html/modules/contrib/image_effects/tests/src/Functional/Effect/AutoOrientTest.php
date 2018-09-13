<?php

namespace Drupal\Tests\image_effects\Functional\Effect;

use Drupal\Tests\image_effects\Functional\ImageEffectsTestBase;

/**
 * Auto Orientation effect test.
 *
 * @group Image Effects
 */
class AutoOrientTest extends ImageEffectsTestBase {

  /**
   * Test effect on required toolkits.
   *
   * @param string $toolkit_id
   *   The id of the toolkit to set up.
   * @param string $toolkit_config
   *   The config object of the toolkit to set up.
   * @param array $toolkit_settings
   *   The settings of the toolkit to set up.
   *
   * @dataProvider providerToolkits
   */
  public function testOnToolkits($toolkit_id, $toolkit_config, array $toolkit_settings) {
    $this->changeToolkit($toolkit_id, $toolkit_config, $toolkit_settings);
  }

  /**
   * Auto Orientation effect test.
   *
   * @depends testOnToolkits
   */
  public function testAutoOrientEffect() {
    // Add Auto Orient effect to the test image style.
    $effect = [
      'id' => 'image_effects_auto_orient',
      'data' => [
        'scan_exif' => TRUE,
      ],
    ];
    $this->addEffectToTestStyle($effect);

    // Add a scale effect too.
    $effect = [
      'id' => 'image_scale',
      'data' => [
        'width' => 200,
        'upscale' => TRUE,
      ],
    ];
    $this->addEffectToTestStyle($effect);

    $test_data = [
      // Test a JPEG image with EXIF data.
      [
        'test_file' => $this->getTestImageCopyUri('/tests/images/portrait-painting.jpg', 'image_effects'),
        'original_width' => 640,
        'original_height' => 480,
        'derivative_width' => 200,
        'derivative_height' => 267,
      ],
      // Test a JPEG image without EXIF data.
      [
        'test_file' => $this->getTestImageCopyUri('/files/image-test.jpg', 'simpletest'),
        'original_width' => 40,
        'original_height' => 20,
        'derivative_width' => 200,
        'derivative_height' => 100,
      ],
      // Test a non-EXIF image.
      [
        'test_file' => $this->getTestImageCopyUri('/files/image-1.png', 'simpletest'),
        'original_width' => 360,
        'original_height' => 240,
        'derivative_width' => 200,
        'derivative_height' => 133,
      ],
    ];

    foreach ($test_data as $data) {
      // Get URI of test file.
      $original_uri = $data['test_file'];

      // Test source image dimensions.
      $image = $this->imageFactory->get($original_uri);
      $this->assertEqual($data['original_width'], $image->getWidth());
      $this->assertEqual($data['original_height'], $image->getHeight());

      // Get expected derivative URL.
      $derivative_url = file_url_transform_relative($this->testImageStyle->buildUrl($original_uri));

      // Check that ::transformDimensions returns expected dimensions.
      $variables = [
        '#theme' => 'image_style',
        '#style_name' => 'image_effects_test',
        '#uri' => $original_uri,
        '#width' => $image->getWidth(),
        '#height' => $image->getHeight(),
      ];
      $this->assertEqual('<img src="' . $derivative_url . '" width="' . $data['derivative_width'] . '" height="' . $data['derivative_height'] . '" alt="" class="image-style-image-effects-test" />', $this->getImageTag($variables));

      // Check that ::applyEffect generates image with expected dimensions.
      $derivative_uri = $this->testImageStyle->buildUri($original_uri);
      $this->testImageStyle->createDerivative($original_uri, $derivative_uri);
      $image = $this->imageFactory->get($derivative_uri);
      $this->assertEqual($data['derivative_width'], $image->getWidth());
      $this->assertEqual($data['derivative_height'], $image->getHeight());
    }
  }

  /**
   * Auto Orientation effect test, all EXIF orientation tags.
   *
   * @depends testOnToolkits
   */
  public function testAutoOrientAllTags() {
    // Add Auto Orient effect to the test image style.
    $effect = [
      'id' => 'image_effects_auto_orient',
      'data' => [
        'scan_exif' => TRUE,
      ],
    ];
    $this->addEffectToTestStyle($effect);

    $test_data = [];
    for ($i = 1; $i < 9; $i++) {
      $test_data[$i]['test_file'] = drupal_get_path('module', 'image_effects') . "/tests/images/image-test-exif-orientation-$i.jpeg";
    }

    foreach ($test_data as $data) {
      // Get URI of test file.
      $original_uri = $data['test_file'];

      // Check that ::applyEffect generates image with expected dimensions and
      // colors at corners.
      $derivative_uri = $this->testImageStyle->buildUri($original_uri);
      $this->testImageStyle->createDerivative($original_uri, $derivative_uri);
      $image = $this->imageFactory->get($derivative_uri, 'gd');
      $this->assertEqual(120, $image->getWidth());
      $this->assertEqual(60, $image->getHeight());
      $this->assertColorsAreClose($this->red, $this->getPixelColor($image, 0, 0), 10);
      $this->assertColorsAreClose($this->green, $this->getPixelColor($image, 119, 0), 10);
      $this->assertColorsAreClose($this->yellow, $this->getPixelColor($image, 0, 59), 10);
      $this->assertColorsAreClose($this->blue, $this->getPixelColor($image, 119, 59), 10);
    }
  }

}
