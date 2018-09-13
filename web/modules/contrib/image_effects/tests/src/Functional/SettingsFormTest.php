<?php

namespace Drupal\Tests\image_effects\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Settings form test.
 *
 * @group Image Effects
 */
class SettingsFormTest extends BrowserTestBase {

  public static $modules = ['image_effects', 'jquery_colorpicker'];

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();
    // Create a user and log it in.
    $this->adminUser = $this->drupalCreateUser([
      'administer site configuration',
      'administer image styles',
    ]);
    $this->drupalLogin($this->adminUser);
  }

  /**
   * Settings form test.
   */
  public function testSettingsForm() {
    $admin_path = '/admin/config/media/image_effects';

    // Get the settings form.
    $this->drupalGet($admin_path);

    // Change the default color selector.
    $edit = [
      'settings[color_selector][plugin_id]' => 'farbtastic',
    ];
    $this->drupalPostForm(NULL, $edit, t('Save configuration'));

    // Check config changed.
    $this->assertEqual('farbtastic', \Drupal::config('image_effects.settings')->get('color_selector.plugin_id'));

    // Change the default image selector.
    $config = \Drupal::configFactory()->getEditable('image_effects.settings');
    $config->set('image_selector.plugin_id', 'dropdown')->save();
    $this->drupalGet($admin_path);
    $edit = [
      'settings[image_selector][plugin_settings][path]' => 'private://',
    ];
    $this->drupalPostForm(NULL, $edit, t('Save configuration'));

    // Check config changed.
    $this->assertEqual(['path' => 'private://'], \Drupal::config('image_effects.settings')->get('image_selector.plugin_settings.dropdown'));

    // Change the default font selector.
    $config = \Drupal::configFactory()->getEditable('image_effects.settings');
    $config->set('font_selector.plugin_id', 'dropdown')->save();
    $this->drupalGet($admin_path);
    $edit = [
      'settings[font_selector][plugin_settings][path]' => 'public://',
    ];
    $this->drupalPostForm(NULL, $edit, t('Save configuration'));

    // Check config changed.
    $this->assertEqual(['path' => 'public://'], \Drupal::config('image_effects.settings')->get('font_selector.plugin_settings.dropdown'));
  }

  /**
   * Test JQuery Colorpicker color selector.
   */
  public function testJqueryColorpickerSelector() {
    $admin_path = '/admin/config/media/image_effects';

    // Get the settings form.
    $this->drupalGet($admin_path);

    // Change the default color selector.
    $edit = [
      'settings[color_selector][plugin_id]' => 'jquery_colorpicker',
    ];
    $this->drupalPostForm(NULL, $edit, t('Save configuration'));

    // Check config changed.
    $this->assertEqual('jquery_colorpicker', \Drupal::config('image_effects.settings')->get('color_selector.plugin_id'));

    // Verify that the 'jquery_colorpicker' module cannot be uninstalled.
    $this->assertNotEqual([], \Drupal::service('module_installer')->validateUninstall(['jquery_colorpicker']));

    // Back to the default color selector.
    $edit = [
      'settings[color_selector][plugin_id]' => 'html_color',
    ];
    $this->drupalPostForm(NULL, $edit, t('Save configuration'));

    // Verify that the 'jquery_colorpicker' module can be uninstalled now.
    $this->assertTrue(\Drupal::service('module_installer')->uninstall(['jquery_colorpicker']));
  }

}
