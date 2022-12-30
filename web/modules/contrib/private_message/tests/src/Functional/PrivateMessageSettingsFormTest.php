<?php

namespace Drupal\Tests\private_message\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests for the Lorem Ipsum module.
 *
 * @group private_message
 */
class PrivateMessageSettingsFormTest extends BrowserTestBase {
  /**
   * {@inheritdoc}
   */

  protected $defaultTheme = 'stark';
  /**
   * Modules to install.
   *
   * @var array
   */
  public static $modules = ['private_message'];

  /**
   * The User used for the test.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  private $adminUser;

  /**
   * The User used for the test.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  private $user;

  /**
   * SetUp the test class.
   */
  public function setUp() {
    parent::setUp();
    $this->user = $this->DrupalCreateUser();
    $this->adminUser = $this->DrupalCreateUser([
      'administer site configuration',
      'administer private message module',
    ]);
  }

  /**
   * Tests that the settings page can be reached.
   */
  public function testSettingsPageExists() {
    $this->drupalLogin($this->user);
    $this->drupalGet('admin/config/private-message/config');
    $this->assertResponse(403);

    $this->drupalLogin($this->adminUser);
    $this->drupalGet('admin/config/private-message/config');
    $this->assertResponse(200);
  }

  /**
   * Tests the config form.
   */
  public function testConfigForm() {
    $this->drupalLogin($this->adminUser);
    $this->drupalGet('admin/config/private-message/config');
    $this->assertResponse(200);

    // Test form submission.
    $this->drupalPostForm(NULL, [], t('Save configuration'));
    $this->assertText(
      'The configuration options have been saved.'
    );
  }

}
