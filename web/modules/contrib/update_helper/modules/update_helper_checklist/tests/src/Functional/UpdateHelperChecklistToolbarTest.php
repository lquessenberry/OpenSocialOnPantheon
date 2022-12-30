<?php

namespace Drupal\Tests\update_helper_checklist\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests the toolbar integration.
 *
 * @see update_helper_checklist_toolbar()
 * @group update_helper_checklist
 */
class UpdateHelperChecklistToolbarTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'toolbar',
    'update_helper_checklist',
    'update_helper_checklist_test',
    'test_page_test'
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests for a tab and tray provided by a module implementing hook_toolbar().
   */
  public function testUpdateHelperChecklistToolbar() {
    $this->drupalGet('test-page');
    $this->assertSession()->statusCodeEquals(200);

    // Assert that the toolbar is present in the HTML.
    $this->assertSession()->elementNotExists('css', 'div#toolbar-administration');
    $this->assertSession()->responseNotContains('id="toolbar-administration"');

    // Test that a user with access to toolbar and updates does not see anything
    // if there are no updates pending.
    $this->drupalLogin($this->drupalCreateUser(['access toolbar', 'view update_helper_checklist checklistapi checklist']));
    $this->drupalGet('test-page');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->elementTextNotContains('css', 'div#toolbar-administration', 'Pending updates');

    // Test that a user with access to toolbar and updates see pending updates.
    $checklist = checklistapi_checklist_load('update_helper_checklist');
    $checklist->clearSavedProgress();
    $this->drupalGet('test-page');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->elementTextContains('css', 'div#toolbar-administration', 'Pending updates');

    // Test that a user with access to toolbar but not the updates does not see
    // anything.
    $this->drupalLogin($this->drupalCreateUser(['access toolbar']));
    $this->drupalGet('test-page');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->elementTextNotContains('css', 'div#toolbar-administration', 'Pending updates');
  }

}
