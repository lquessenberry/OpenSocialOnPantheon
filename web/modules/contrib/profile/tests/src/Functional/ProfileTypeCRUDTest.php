<?php

namespace Drupal\Tests\profile\Functional;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Component\Utility\Unicode;
use Drupal\profile\Entity\ProfileType;
use Drupal\profile\Entity\Profile;

/**
 * Tests basic CRUD functionality of profile types.
 *
 * @group profile
 */
class ProfileTypeCRUDTest extends ProfileTestBase {

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
    $this->assertSession()->statusCodeEquals(200);
  }

  /**
   * Tests CRUD operations for profile types through the UI.
   */
  public function testUi() {
    $this->drupalLogin($this->adminUser);

    // Create a new profile type.
    $this->drupalGet('admin/config/people/profiles');
    $this->assertSession()->statusCodeEquals(200);
    $this->clickLink(t('Add profile type'));

    $this->assertSession()->addressEquals('admin/config/people/profiles/add');
    $id = Unicode::strtolower($this->randomMachineName());
    $label = $this->getRandomGenerator()->word(10);
    $edit = [
      'id' => $id,
      'label' => $label,
    ];
    $this->drupalPostForm(NULL, $edit, t('Save'));
    $this->assertSession()->addressEquals('admin/config/people/profiles');
    $this->assertSession()->responseContains(new FormattableMarkup('%label profile type has been created.', ['%label' => $label]));
    $this->assertSession()->linkByHrefExists("admin/config/people/profiles/manage/$id");
    $this->assertSession()->linkByHrefExists("admin/config/people/profiles/manage/$id/fields");
    $this->assertSession()->linkByHrefExists("admin/config/people/profiles/manage/$id/display");
    $this->assertSession()->linkByHrefExists("admin/config/people/profiles/manage/$id/delete");

    // Edit the new profile type.
    $this->drupalGet("admin/config/people/profiles/manage/$id");
    $this->assertSession()->responseContains(new FormattableMarkup('Edit %label profile type', ['%label' => $label]));
    $this->getSession()->getPage()->checkField('Include in user registration form');
    $this->getSession()->getPage()->checkField('Create a new revision when a profile is modified');
    $this->submitForm([], 'Save');
    $this->assertSession()->addressEquals('admin/config/people/profiles');
    $this->assertSession()->responseContains(new FormattableMarkup('%label profile type has been updated.', ['%label' => $label]));

    $profile_type = ProfileType::load($id);
    $this->assertEquals($label, $profile_type->label());
    $this->assertTrue($profile_type->getRegistration());
    $this->assertTrue($profile_type->shouldCreateNewRevision());

    // Delete profile type.
    // First check with existing profile of type.
    $profile = Profile::create([
      'type' => $id,
      'uid' => $this->adminUser->id(),
    ]);
    $profile->save();
    $this->drupalGet("admin/config/people/profiles/manage/$id/delete");
    $this->assertSession()->responseContains(new FormattableMarkup('%label is used by 1 profile on your site. You can not remove this profile type until you have removed all of the %label profiles', ['%label' => $label]));

    // Delete profile and delete profile type.
    $profile->delete();
    $this->drupalGet("admin/config/people/profiles/manage/$id/delete");
    $this->assertSession()->responseContains('This action cannot be undone.');
    $this->assertSession()->linkByHrefExists('admin/config/people/profiles');
    $this->submitForm([], 'Delete');
    $this->assertSession()->addressEquals('admin/config/people/profiles');
    $this->assertSession()->responseContains(new FormattableMarkup('The profile type %label has been deleted.', ['%label' => $label]));

    $profile_type = ProfileType::load($id);
    $this->assertNull($profile_type);
  }

}
