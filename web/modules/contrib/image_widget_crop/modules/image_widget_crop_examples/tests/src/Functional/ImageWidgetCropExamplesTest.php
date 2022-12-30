<?php

namespace Drupal\Tests\image_widget_crop_examples\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests image_widget_crop_examples.
 *
 * @group image_widget_crop_examples
 *
 * @ingroup media
 */
class ImageWidgetCropExamplesTest extends BrowserTestBase {

  /**
   * Modules to install.
   *
   * @var array
   */
  public static $modules = [
    'menu_ui',
    'path',
    'media',
  ];

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();
    // Theme needs to be set before enabling image_widget_crop_examples because
    // of dependency.
    \Drupal::service('theme_installer')->install(['bartik']);
    $this->config('system.theme')
      ->set('default', 'bartik')
      ->save();

    $example_module_is_installed = \Drupal::service('module_installer')->install(['image_widget_crop_examples']);
    $this->assertTrue($example_module_is_installed, 'image_widget_crop_examples installed.');
    \Drupal::service('router.builder')->rebuild();
  }

  /**
   * Tests if image_widget_crop_example is correctly installed.
   */
  public function testInstalled() {
    $this->drupalGet('');
    $this->assertSession()->titleEquals('Image Widget Crop examples | Drupal');
    $this->assertSession()->pageTextContains('Image Widget Crop examples');
    $this->assertSession()->pageTextContains('Welcome to Image Widget Crop example.');
    $this->assertSession()->pageTextContains('Image Widget Crop provides an interface for using the features of the Crop API.');
    $this->assertSession()->pageTextContains('You can test the functionality with custom content types created for the demonstration of features from Image Widget Crop:');
  }

}
