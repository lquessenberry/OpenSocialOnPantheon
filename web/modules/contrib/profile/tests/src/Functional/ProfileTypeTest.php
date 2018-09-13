<?php

namespace Drupal\Tests\profile\Functional;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Component\Utility\Unicode;

/**
 * Tests basic CRUD functionality of profile types.
 *
 * @group profile
 */
class ProfileTypeTest extends ProfileTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->adminUser = $this->drupalCreateUser([
      'access user profiles',
      'administer profile types',
      'administer profile fields',
      'administer profile display',
    ]);
  }

  /**
   * Verify that routes are created for the profile type.
   */
  public function testRoutes() {
    $this->drupalLogin($this->adminUser);
    $type = $this->createProfileType($this->randomMachineName());
    \Drupal::service('router.builder')->rebuildIfNeeded();
    $this->drupalGet("user/{$this->adminUser->id()}/{$type->id()}");
    $this->assertResponse(200);
  }

  /**
   * Tests CRUD operations for profile types through the UI.
   */
  public function testUi() {
    $this->drupalLogin($this->adminUser);

    // Create a new profile type.
    $this->drupalGet('admin/config/people/profiles');
    $this->assertResponse(200);
    $this->clickLink(t('Add profile type'));

    $this->assertUrl('admin/config/people/profiles/add');
    $id = Unicode::strtolower($this->randomMachineName());
    $label = $this->randomString();
    $edit = [
      'id' => $id,
      'label' => $label,
    ];
    $this->drupalPostForm(NULL, $edit, t('Save'));
    $this->assertUrl('admin/config/people/profiles');
    $this->assertRaw(new FormattableMarkup('%label profile type has been created.', ['%label' => $label]));
    $this->assertLinkByHref("admin/config/people/profiles/manage/$id");
    $this->assertLinkByHref("admin/config/people/profiles/manage/$id/fields");
    $this->assertLinkByHref("admin/config/people/profiles/manage/$id/display");
    $this->assertLinkByHref("admin/config/people/profiles/manage/$id/delete");

    // Edit the new profile type.
    $this->drupalGet("admin/config/people/profiles/manage/$id");
    $this->assertRaw(new FormattableMarkup('Edit %label profile type', ['%label' => $label]));
    $edit = [
      'registration' => 1,
    ];
    $this->drupalPostForm(NULL, $edit, t('Save'));
    $this->assertUrl('admin/config/people/profiles');
    $this->assertRaw(new FormattableMarkup('%label profile type has been updated.', ['%label' => $label]));
  }

}
