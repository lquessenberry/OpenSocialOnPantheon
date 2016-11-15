<?php

namespace Drupal\flag\Tests;


/**
 * Tests for the base entity Flag Type plugin.
 *
 * @group flag
 */
class ShowOnEntityFormTest extends FlagTestBase {

  /**
   * The flag to be flagged and unflagged.
   *
   * @var FlagInterface
   */
  protected $flag;

  /**
   * Tests if flags appear on the entity form.
   */
  public function testEntityForm() {
    // Login as the admin user.
    $this->drupalLogin($this->adminUser);

    // Create the flag with show_on_form, and grant permissions.
    $edit = [
      'bundles' => [$this->nodeType],
      'flagTypeConfig' => [
        'show_as_field' => TRUE,
        'show_on_form' => TRUE,
        'show_contextual_link' => FALSE,
        ],
    ];
    $flag = $this->createFlagFromArray($edit);
    $this->grantFlagPermissions($flag);
    $flag_checkbox_id = 'edit-flag-' . $flag->id();

    // Create a node and get the ID.
    $node = $this->createNode(['type' => $this->nodeType]);
    $node_id = $node->id();
    $node_edit_path = 'node/' . $node_id . '/edit';

    // See if the form element exists.
    $this->drupalGet($node_edit_path);
    $this->assertField($flag_checkbox_id, $this->t('The flag checkbox exists on the entity form.'));

    // See if flagging on the form works.
    $edit = [
      'flag[' . $flag->id() . ']' => TRUE,
    ];
    $this->drupalPostForm($node_edit_path, $edit, $this->t('Save and keep published'));

    // Check to see if the checkbox reflects the state correctly.
    $this->drupalGet($node_edit_path);
    $this->assertFieldChecked($flag_checkbox_id, $this->t('The flag checkbox is checked on the entity form.'));

    // See if unflagging on the form works.
    $edit = [
      'flag[' . $flag->id() . ']' => FALSE,
    ];
    $this->drupalPostForm($node_edit_path, $edit, $this->t('Save and keep published'));

    // Go back to the node edit page and check if the flag checkbox is updated.
    $this->drupalGet($node_edit_path);
    $this->assertNoFieldChecked($flag_checkbox_id, $this->t('The flag checkbox is unchecked on the entity form.'));
  }

}
