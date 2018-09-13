<?php

namespace Drupal\Tests\image_effects\Functional\Effect;

use Drupal\Tests\image_effects\Functional\ImageEffectsTestBase;

/**
 * Background effect test.
 *
 * @group Image Effects
 */
class BackgroundTest extends ImageEffectsTestBase {

  /**
   * {@inheritdoc}
   */
  public function providerToolkits() {
    $toolkits = parent::providerToolkits();
    // @todo This effect does not work on GraphicsMagick.
    unset($toolkits['ImageMagick-graphicsmagick']);
    return $toolkits;
  }

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
   * Background effect test.
   *
   * @depends testOnToolkits
   */
  public function testBackgroundEffect() {
    $original_uri = $this->getTestImageCopyUri('/files/image-test.png', 'simpletest');
    $background_uri = $this->getTestImageCopyUri('/files/image-1.png', 'simpletest');

    $effect = [
      'id' => 'image_effects_background',
      'data' => [
        'placement' => 'left-top',
        'x_offset' => 0,
        'y_offset' => 0,
        'opacity' => 100,
        'background_image' => $background_uri,
      ],
    ];
    $uuid = $this->addEffectToTestStyle($effect);

    // Check that ::transformDimensions returns expected dimensions.
    $image = $this->imageFactory->get($original_uri);
    $this->assertEqual(40, $image->getWidth());
    $this->assertEqual(20, $image->getHeight());
    $derivative_url = file_url_transform_relative($this->testImageStyle->buildUrl($original_uri));
    $variables = [
      '#theme' => 'image_style',
      '#style_name' => 'image_effects_test',
      '#uri' => $original_uri,
      '#width' => $image->getWidth(),
      '#height' => $image->getHeight(),
    ];
    $this->assertEqual('<img src="' . $derivative_url . '" width="360" height="240" alt="" class="image-style-image-effects-test" />', $this->getImageTag($variables));

    // Check that ::applyEffect generates image with expected canvas.
    $derivative_uri = $this->testImageStyle->buildUri($original_uri);
    $this->testImageStyle->createDerivative($original_uri, $derivative_uri);
    $image = $this->imageFactory->get($derivative_uri, 'gd');
    $this->assertEqual(360, $image->getWidth());
    $this->assertEqual(240, $image->getHeight());
    $this->assertColorsAreEqual($this->red, $this->getPixelColor($image, 0, 0));
    $this->assertColorsAreEqual($this->green, $this->getPixelColor($image, 39, 0));
    $this->assertColorsAreEqual([185, 185, 185, 0], $this->getPixelColor($image, 0, 19));
    $this->assertColorsAreEqual($this->blue, $this->getPixelColor($image, 39, 19));

    // Remove effect.
    $this->removeEffectFromTestStyle($uuid);

    // Toolkit-specific tests.
    switch ($this->imageFactory->getToolkitId()) {
      case 'gd':
        // For the GD toolkit, test we are not left with orphan resource after
        // applying the operation.
        $image = $this->imageFactory->get($original_uri);
        // Store the original GD resource.
        $old_res = $image->getToolkit()->getResource();
        // Apply the operation.
        $image->apply('background', [
          'x_offset' => 0,
          'y_offset' => 0,
          'opacity' => 100,
          'background_image' => $this->imageFactory->get($background_uri),
        ]);
        // The operation replaced the resource, check that the old one has
        // been destroyed.
        $new_res = $image->getToolkit()->getResource();
        $this->assertTrue(is_resource($new_res));
        $this->assertNotEqual($new_res, $old_res);
        $this->assertFalse(is_resource($old_res));
        break;

      case 'imagemagick':
        // For the Imagemagick toolkit, toolkit should return background
        // image dimensions after applying the operation, but before
        // saving.
        $image = $this->imageFactory->get($original_uri);
        // Apply the operation.
        $image->apply('background', [
          'x_offset' => 0,
          'y_offset' => 0,
          'opacity' => 100,
          'background_image' => $this->imageFactory->get($background_uri),
        ]);
        $this->assertEqual(360, $image->getToolkit()->getWidth());
        $this->assertEqual(240, $image->getToolkit()->getHeight());
        break;

    }
  }

}
