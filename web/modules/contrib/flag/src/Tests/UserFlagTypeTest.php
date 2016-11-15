<?php

namespace Drupal\flag\Tests;

use Drupal\flag\Tests\FlagTestBase;

/**
 * Tests user flag type integrations.
 *
 * @group flag
 */
class UserFlagTypeTest extends FlagTestBase {

  /**
   * The flag to be added.
   *
   * @var \Drupal\flag\FlagInterface
   */
  protected $flag;

  /**
   * Tests that when adding a flag for users the relevant checkboxes are added.
   */
  public function testFlagSelfCheckbox() {
    // Login as the admin user.
    $this->drupalLogin($this->adminUser);

    $this->drupalPostForm('admin/structure/flags/add', [
      'flag_entity_type' => 'entity:user',
    ], $this->t('Continue'));

    $this->assertText($this->t('Users may flag themselves'));

    $this->assertText($this->t('Display link on user profile page'));
  }

  /**
   * Tests that user can flag themselves when and only when appropiate.
   */
  public function testFlagSelf() {
    // Login as the admin user.
    $this->drupalLogin($this->adminUser);

    $flag = $this->createFlagFromArray([
      'link_type' => 'reload',
      'entity_type' => 'user',
      'bundles' => array_keys(\Drupal::service('entity_type.bundle.info')->getBundleInfo('user')),
      'flag_type' => $this->getFlagType('user'),
      'show_on_profile' => TRUE,
      ]);
    $this->grantFlagPermissions($flag);

    $this->drupalGet('admin/structure/flags/manage/' . $flag->id());
    $this->assertFieldChecked('edit-access-uid');

    $this->drupalGet('user/' . $this->adminUser->id());

    $this->assertLink($flag->getFlagShortText());

    $edit = [
      'access_uid' => FALSE,
    ];
    $this->drupalPostForm('admin/structure/flags/manage/' . $flag->id(), $edit, $this->t('Save Flag'));

    $this->drupalGet('admin/structure/flags/manage/' . $flag->id());
    $this->assertNoFieldChecked('edit-access-uid');

    $this->drupalGet('user/' . $this->adminUser->id());
    $this->assertNoLink($flag->getFlagShortText());
  }

}
