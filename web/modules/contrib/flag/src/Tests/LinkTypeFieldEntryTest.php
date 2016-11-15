<?php

namespace Drupal\flag\Tests;

use Drupal\field_ui\Tests\FieldUiTestTrait;

/**
 * Test the Field Entry link type.
 *
 * @group flag
 */
class LinkTypeFieldEntryTest extends FlagTestBase {

  use FieldUiTestTrait;

  protected $nodeId;

  protected $flagConfirmMessage = 'Flag test label 123?';
  protected $flagDetailsMessage = 'Enter flag test label 123 details';
  protected $unflagConfirmMessage = 'Unflag test label 123?';

  protected $flagFieldId = 'flag_text_field';
  protected $flagFieldLabel = 'Flag Text Field';
  protected $flagFieldValue;

  /**
   * The flag object.
   *
   * @var \Drupal\flag\FlagInterface
   */
  protected $flag;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    // The breadcrumb block is needed for FieldUiTestTrait's tests.
    $this->drupalPlaceBlock('system_breadcrumb_block');
    $this->drupalPlaceBlock('local_tasks_block');
    $this->drupalPlaceBlock('page_title_block');
  }

  /**
   * Create a new flag with the Field Entry type, and add fields.
   */
  public function testCreateFieldEntryFlag() {
    $this->drupalLogin($this->adminUser);
    $this->doCreateFlag();
    $this->doAddFields();
    $this->doFlagNode();
    $this->doEditFlagField();
    $this->doBadEditFlagField();
    $this->doUnflagNode();
  }

  /**
   * Test the flag field entry plugin UI.
   */
  public function doFlagUIfieldPlugin() {
    $this->drupalPostForm('admin/structure/flags/add', [], t('Continue'));

    // Update the flag.
    $edit = [
      'link_type' => 'field_entry',
    ];
    $this->drupalPostAjaxForm(NULL, $edit, 'link_type');

    // Check confirm form field entry.
    $this->assertText(t('Flag confirmation message'));
    $this->assertText(t('Enter flagging details message'));
    $this->assertText(t('Unflag confirmation message'));
  }

  /**
   * Create a node type and flag.
   */
  public function doCreateFlag() {
    $edit = [
      'bundles' => [$this->nodeType],
      'linkTypeConfig' => [
        'flag_confirmation' => $this->flagConfirmMessage,
        'unflag_confirmation' => $this->unflagConfirmMessage,
        'edit_flagging' => $this->flagDetailsMessage,
      ],
      'link_type' => 'field_entry',
    ];
    $this->flag = $this->createFlagFromArray($edit);
  }

  /**
   * Add fields to flag.
   */
  public function doAddFields() {
    $flag_id = $this->flag->id();

    // Check the Field UI tabs appear on the flag edit page.
    $this->drupalGet('admin/structure/flags/manage/' . $flag_id);
    $this->assertText(t("Manage fields"), "The Field UI tabs appear on the flag edit form page.");

    $this->fieldUIAddNewField('admin/structure/flags/manage/' . $flag_id, $this->flagFieldId, $this->flagFieldLabel, 'text');
  }

  /**
   * Create a node and flag it.
   */
  public function doFlagNode() {
    $node = $this->drupalCreateNode(['type' => $this->nodeType]);
    $this->nodeId = $node->id();

    // Grant the flag permissions to the authenticated role, so that both
    // users have the same roles and share the render cache.
    $this->grantFlagPermissions($this->flag);

    // Create and login a new user.
    $user_1 = $this->drupalCreateUser();
    $this->drupalLogin($user_1);

    // Click the flag link.
    $this->drupalGet('node/' . $this->nodeId);
    $this->clickLink($this->flag->getFlagShortText());

    // Check if we have the confirm form message displayed.
    $this->assertText($this->flagConfirmMessage);

    // Enter the field value and submit it.
    $this->flagFieldValue = $this->randomString();
    $edit = [
      'field_' . $this->flagFieldId . '[0][value]' => $this->flagFieldValue,
    ];
    $this->drupalPostForm(NULL, $edit, t('Create Flagging'));

    // Check that the node is flagged.
    $this->assertLink($this->flag->getUnflagShortText());
  }

  /**
   * Edit the field value of the existing flagging.
   */
  public function doEditFlagField() {
    $flag_id = $this->flag->id();

    $this->drupalGet('node/' . $this->nodeId);

    // Get the details form.
    $this->clickLink($this->flag->getUnflagShortText());
    $this->assertUrl('flag/details/edit/' . $flag_id . '/' . $this->nodeId, [
      'query' => [
        'destination' => 'node/' . $this->nodeId,
      ],
    ]);

    // See if the details message is displayed.
    $this->assertText($this->flagDetailsMessage);

    // See if the field value was preserved.
    $this->assertFieldByName('field_' . $this->flagFieldId . '[0][value]', $this->flagFieldValue);

    // Update the field value.
    $this->flagFieldValue = $this->randomString();
    $edit = [
      'field_' . $this->flagFieldId . '[0][value]' => $this->flagFieldValue,
    ];
    $this->drupalPostForm(NULL, $edit, t('Update Flagging'));

    // Get the details form.
    $this->drupalGet('flag/details/edit/' . $flag_id . '/' . $this->nodeId);

    // See if the field value was preserved.
    $this->assertFieldByName('field_' . $this->flagFieldId . '[0][value]', $this->flagFieldValue);
  }

  /**
   * Assert editing an invalid flagging throws an exception.
   */
  public function doBadEditFlagField() {
    $flag_id = $this->flag->id();

    // Test a good flag ID param, but a bad flaggable ID param.
    $this->drupalGet('flag/details/edit/' . $flag_id . '/-9999');
    $this->assertResponse('404', 'Editing an invalid flagging path: good flag, bad entity.');

    // Test a bad flag ID param, but a good flaggable ID param.
    $this->drupalGet('flag/details/edit/jibberish/' . $this->nodeId);
    $this->assertResponse('404', 'Editing an invalid flagging path: bad flag, good entity');

    // Test editing a unflagged entity.
    $unlinked_node = $this->drupalCreateNode(['type' => $this->nodeType]);
    $this->drupalGet('flag/details/edit/' . $flag_id . '/' . $unlinked_node->id());
    $this->assertResponse('404', 'Editing an invalid flagging path: good flag, good entity, but not flagged');
  }

  /**
   * Test unflagging content.
   */
  public function doUnflagNode() {

    // Navigate to the node page.
    $this->drupalGet('node/' . $this->nodeId);

    // Click the Unflag link.
    $this->clickLink($this->flag->getUnflagShortText());

    // Click the delete link.
    $this->clickLink($this->t('Delete Flagging'));

    // Check if we have the confirm form message displayed.
    $this->assertText($this->unflagConfirmMessage);

    // Submit the confirm form.
    $this->drupalPostForm(NULL, [], $this->t('Unflag'));
    $this->assertResponse(200);

    // Check that the node is no longer flagged.
    $this->drupalGet('node/' . $this->nodeId);
    $this->assertLink($this->flag->getFlagShortText());
  }

}
