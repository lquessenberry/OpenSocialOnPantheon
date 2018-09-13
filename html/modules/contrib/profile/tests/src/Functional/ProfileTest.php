<?php

namespace Drupal\Tests\profile\Functional;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Url;
use Drupal\profile\Entity\Profile;
use Drupal\user\Entity\User;

/**
 * Tests various CRUD for Profile entity in browser.
 *
 * @group profile
 */
class ProfileTest extends ProfileTestBase {

  /**
   * Tests creating and editing a profile.
   */
  public function testCreateEditProfile() {
    $this->drupalLogin($this->adminUser);

    $profile_fullname = $this->randomString();
    $create_url = Url::fromRoute('entity.profile.type.user_profile_form', [
      'user' => $this->loggedInUser->id(),
      'profile_type' => $this->type->id(),
    ]);
    $this->drupalGet($create_url->toString());
    $this->assertSession()->titleEquals("Create {$this->type->label()} | Drupal");
    $this->assertSession()->buttonNotExists('Save and make default');
    $edit = [
      'profile_fullname[0][value]' => $profile_fullname,
    ];
    $this->submitForm($edit, 'Save');

    /** @var \Drupal\profile\ProfileStorageInterface $storage */
    $storage = $this->container->get('entity_type.manager')->getStorage('profile');

    $profile = $storage->loadDefaultByUser($this->loggedInUser, $this->type->id());
    $this->assertEquals($profile_fullname, $profile->get('profile_fullname')->value);

    $this->drupalGet($profile->toUrl('edit-form')->toString());
    $this->assertSession()->titleEquals("Edit {$this->type->label()} profile #{$profile->id()} | Drupal");

    $profile_fullname = $this->randomString();
    $edit = [
      'profile_fullname[0][value]' => $profile_fullname,
    ];
    $this->submitForm($edit, 'Save');

    $storage->resetCache([$profile->id()]);
    $profile = $storage->loadDefaultByUser($this->loggedInUser, $this->type->id());
    $this->assertEquals($profile_fullname, $profile->get('profile_fullname')->value);
  }

  /**
   * Tests that a profile belonging to an anonymous user can be edited.
   */
  public function testAnonymousProfileEdit() {
    $profile = $this->createProfile($this->type, User::getAnonymousUser());

    $this->drupalLogin($this->adminUser);
    $this->drupalGet($profile->toUrl('edit-form')->toString());
    $profile_fullname = $this->randomString();
    $edit = [
      'profile_fullname[0][value]' => $profile_fullname,
    ];
    $this->submitForm($edit, 'Save');
    $this->assertSession()->addressEquals($profile->toUrl('collection')->toString());
  }

  /**
   * Tests entity.profile.type.user_profile_form route access.
   */
  public function testUserProfileFormAccess() {
    $id = $this->type->id();
    $test_user1 = $this->drupalCreateUser([
      "view own $id profile",
      "create $id profile",
      "update own $id profile",
      "delete own $id profile",
    ]);
    $test_user2 = $this->drupalCreateUser([
      "view own $id profile",
      "create $id profile",
      "update own $id profile",
      "delete own $id profile",
    ]);

    $this->drupalLogin($this->adminUser);
    $this->drupalGet(Url::fromRoute('entity.profile.type.user_profile_form', ['user' => $test_user1->id(), 'profile_type' => $id]));
    $this->assertSession()->statusCodeEquals(200);
    $this->drupalLogin($test_user1);
    $this->drupalGet(Url::fromRoute('entity.profile.type.user_profile_form', ['user' => $test_user1->id(), 'profile_type' => $id]));
    $this->assertSession()->statusCodeEquals(200);
    $this->drupalGet(Url::fromRoute('entity.profile.type.user_profile_form', ['user' => $test_user2->id(), 'profile_type' => $id]));
    $this->assertSession()->statusCodeEquals(403);

    $this->type->setMultiple(TRUE)->save();

    // Add link only appears if there are existing profiles.
    Profile::create([
      'type' => $this->type->id(),
      'uid' => $test_user1->id(),
      'profile_fullname' => $this->randomMachineName(),
    ])->save();
    Profile::create([
      'type' => $this->type->id(),
      'uid' => $test_user2->id(),
      'profile_fullname' => $this->randomMachineName(),
    ])->save();

    $this->drupalLogin($this->adminUser);
    $this->drupalGet(Url::fromRoute('entity.profile.type.user_profile_form', ['user' => $test_user1->id(), 'profile_type' => $id]));
    $this->assertSession()->linkExists(new FormattableMarkup('Add new @type', ['@type' => $this->type->label()]));
    $this->assertSession()->statusCodeEquals(200);
    $this->drupalLogin($test_user1);
    $this->drupalGet(Url::fromRoute('entity.profile.type.user_profile_form', ['user' => $test_user1->id(), 'profile_type' => $id]));
    $this->assertSession()->linkExists(new FormattableMarkup('Add new @type', ['@type' => $this->type->label()]));
    $this->assertSession()->statusCodeEquals(200);
    $this->drupalGet(Url::fromRoute('entity.profile.type.user_profile_form', ['user' => $test_user2->id(), 'profile_type' => $id]));
    $this->assertSession()->linkNotExists(new FormattableMarkup('Add new @type', ['@type' => $this->type->label()]));
    $this->assertSession()->statusCodeEquals(403);

  }

}
