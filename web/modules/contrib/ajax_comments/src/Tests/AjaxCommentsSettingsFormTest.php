<?php

namespace Drupal\ajax_comments\Tests;

use Drupal\Tests\comment\Functional\CommentTestBase;
use Drupal\user\Entity\Role;

/**
 * Tests the \Drupal\ajax_comments\Form\SettingsForm.
 *
 * @group ajax_comments
 */
class AjaxCommentsSettingsFormTest extends CommentTestBase {

  /**
   * Modules to install.
   *
   * @var array
   */
  public static $modules = [
    'system',
    'block',
    'comment',
    'node',
    'ajax_comments',
  ];

   /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    $admin_roles = $this->adminUser->getRoles();
    $admin_role = Role::load(reset($admin_roles));
    $this->grantPermissions($admin_role, ['administer site configuration', 'administer node display']);
  }

  /**
   * Test the \Drupal\ajax_comments\Form\SettingsForm.
   */
  public function testAjaxCommentsSettings() {
    $this->drupalLogin($this->adminUser);
    $this->drupalGet('admin/config/content/ajax_comments');
    // Check that the page loads.
    $this->assertResponse(200);
    $this->assertText(
      t("Enable Ajax Comments on the comment fields' display settings"),
      'The list of bundles appears on the form.'
    );
    $this->clickLink(t('Content: Article'));
    $this->assertUrl('/admin/structure/types/manage/article/display', [], 'There is a link to the entity view display form for articles.');
    $this->assertResponse(200);

    // Open comment settings.
    $this->drupalPostForm(NULL, [], 'comment_settings_edit');
    // Disable ajax comments.
    $this->drupalPostForm(NULL, ['fields[comment][settings_edit_form][third_party_settings][ajax_comments][enable_ajax_comments]' => '0'], 'comment_plugin_settings_update');
    // Save display mode.
    $this->drupalPostForm(NULL, [], t('Save'));
    $this->assertResponse(200);
  }

}
