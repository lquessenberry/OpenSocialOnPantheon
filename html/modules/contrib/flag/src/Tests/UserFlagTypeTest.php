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

    $this->assertText($this->t('Permissions for users to flag themselves.'));

    $this->assertText($this->t('Display link on user profile page'));
  }

  /**
   * Tests that user can flag themselves when and only when appropiate.
   */
  public function testFlagSelf() {

    $flag = $this->createFlagFromArray([
      'link_type' => 'reload',
      'entity_type' => 'user',
      'bundles' => array_keys(\Drupal::service('entity_type.bundle.info')->getBundleInfo('user')),
      'flag_type' => $this->getFlagType('user'),
      'show_on_profile' => TRUE,
      'flagTypeConfig' => [
        // Create extra permissions to self flag.
        'extra_permissions' => ['owner'],
       ],
    ]);

     // User can flag their own work.
    $user = $this->createUser([
      'flag ' . $flag->id() . ' own user account',
      'unflag ' . $flag->id() . ' own user account',
      'administer flags',
      'administer flagging display',
      'administer flagging fields',
      'administer node display',
      'administer nodes',
    ]);

    $this->drupalLogin($user);

    // Check the state of the extra permssions checkbox.
    $this->drupalGet('admin/structure/flags/manage/' . $flag->id());
    $this->assertFieldChecked('edit-extra-permissions-owner');

    // Assert flag appears on the profile page.
    $this->drupalGet('user/' . $user->id());
    $this->assertLink($flag->getShortText('flag'));

    // Uncheck extra permssions.
    $edit = [
      'extra_permissions[owner]' => FALSE,
    ];
    $this->drupalPostForm('admin/structure/flags/manage/' . $flag->id(), $edit, $this->t('Save Flag'));

    // Confirm extra permissions is unchecked.
    $this->drupalGet('admin/structure/flags/manage/' . $flag->id());
    $this->assertNoFieldChecked('edit-extra-permissions-owner');

    // Assert the flag disapears from the profile page.
    $this->drupalGet('user/' . $user->id());
    $this->assertNoLink($flag->getShortText('flag'));
  }

}
