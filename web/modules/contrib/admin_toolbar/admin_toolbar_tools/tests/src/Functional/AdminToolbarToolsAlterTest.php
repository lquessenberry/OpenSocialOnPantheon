<?php

namespace Drupal\Tests\admin_toolbar_tools\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests for the existence of Admin Toolbar tools new links.
 *
 * @group admin_toolbar
 */
class AdminToolbarToolsAlterTest extends BrowserTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'toolbar',
    'admin_toolbar',
    'admin_toolbar_tools',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * A test user with permission to access the administrative toolbar.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $adminUser;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    // Create and log in an administrative user.
    $this->adminUser = $this->drupalCreateUser([
      'access toolbar',
      'access administration pages',
      'administer site configuration',
    ]);
    $this->drupalLogin($this->adminUser);
  }

  /**
   * Tests for the hover of sub menus.
   */
  public function testAdminToolbarTools() {
    // Assert that special menu items are present in the HTML.
    $this->assertSession()->responseContains('class="toolbar-icon toolbar-icon-admin-toolbar-tools-flush"');
  }

}
