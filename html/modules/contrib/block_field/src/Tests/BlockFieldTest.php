<?php

namespace Drupal\block_field\Tests;

use Drupal\simpletest\WebTestBase;

/**
 * Tests block field widgets and formatters.
 *
 * @group block_field
 */
class BlockFieldTest extends WebTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = [
    'node',
    'user',
    'block',
    'block_field',
    'block_field_test',
    'field_ui',
  ];

  /**
   * Tests block field.
   */
  public function testBlockField() {
    $admin_user = $this->drupalCreateUser([
      'access content',
      'administer nodes',
      'administer content types',
      'bypass node access',
      'administer node fields',
    ]);
    $this->drupalLogin($admin_user);

    // Create block field test using the three test blocks.
    // Check that add more and ajax callbacks are working as expected.
    $this->drupalPostForm('node/add/block_field_test', [
      'title[0][value]' => 'Block field test',
    ], t('Add another item'));
    $this->drupalPostForm(NULL, [], t('Add another item'));
    $this->drupalPostForm(NULL, [
      'field_block_field_test[0][plugin_id]' => 'block_field_test_authenticated',
      'field_block_field_test[1][plugin_id]' => 'block_field_test_content',
      'field_block_field_test[2][plugin_id]' => 'block_field_test_time',
    ], t('Add another item'));
    $this->drupalPostForm(NULL, [
      'field_block_field_test[0][plugin_id]' => 'block_field_test_authenticated',
      'field_block_field_test[1][plugin_id]' => 'block_field_test_content',
      'field_block_field_test[2][plugin_id]' => 'block_field_test_time',
    ], t('Add another item'));
    $this->drupalPostForm(NULL, [], t('Save and publish'));

    // Check blocks displayed to authenticated.
    $this->drupalGet('node/1');
    $this->assertRaw('<div class="field field--name-field-block-field-test field--type-block-field field--label-above">');
    $this->assertRaw('<div class="field__label">Block field test</div>');
    $this->assertRaw('<h2>You are logged in as...</h2>');
    $this->assertRaw('<p><span>' . $admin_user->label() . '</span></p>');
    $this->assertRaw('<h2>Block field test content</h2>');
    $this->assertRaw('This block was created at');
    $this->assertRaw('<h2>The time is...</h2>');
    $this->assertPattern('/\d\d:\d\d:\d\d/');

    // Create a block_field_test node.
    $block_node = $this->drupalCreateNode([
      'type' => 'block_field_test',
    ]);

    // Check authenticated block.
    $block_node->field_block_field_test->plugin_id = 'block_field_test_authenticated';
    $block_node->field_block_field_test->settings = [
      'label' => 'Authenticated',
      'label_display' => TRUE,
    ];
    $block_node->save();
    $this->drupalGet('node/' . $block_node->id());
    $this->assertRaw('<h2>Authenticated</h2>');
    $this->assertRaw('<p><span>' . $admin_user->label() . '</span></p>');

    // Check block_field_test_authenticated cache dependency is respected when
    // the user's name is updated.
    $admin_user->setUsername('admin_user');
    $admin_user->save();
    $this->drupalGet('node/' . $block_node->id());
    $this->assertRaw('<h2>Authenticated</h2>');
    $this->assertRaw('<p><span>admin_user</span></p>');

    // Check authenticated block is not visible to anonymous users.
    $this->drupalLogout();
    $this->drupalGet('node/' . $block_node->id());
    $this->assertNoRaw('<h2>Authenticated</h2>');
    $this->assertNoRaw('<p><span>' . $admin_user->label() . '</span></p>');

    // Check content block.
    $block_node->field_block_field_test->plugin_id = 'block_field_test_content';
    $block_node->field_block_field_test->settings = [
      'label' => 'Hello',
      'label_display' => TRUE,
      'content' => '<p>World</p>',
    ];
    $block_node->save();

    $this->drupalGet('node/' . $block_node->id());
    $this->assertRaw('<h2>Hello</h2>');
    $this->assertRaw('<p>World</p>');

    // ISSUE: Drupal's page cache it not respecting the time block max age,
    // so we need to log in to bypass page caching.
    $this->drupalLogin($admin_user);

    // Check time block.
    $block_node->field_block_field_test->plugin_id = 'block_field_test_time';
    $block_node->field_block_field_test->settings = [
      'label' => 'Time',
      'label_display' => TRUE,
    ];
    $block_node->save();

    // Check that time is set.
    $this->drupalGet('node/' . $block_node->id());
    $this->assertPattern('/\d\d:\d\d:\d\d \(\d+\)/');

    // Get the current time.
    preg_match('/\d\d:\d\d:\d\d \(\d+\)/', $this->getRawContent(), $match);
    $time = $match[0];
    $this->assertRaw($time);

    // Have delay test one second so that the time is updated.
    sleep(1);

    // Check that time is never cached by reloading the page.
    $this->drupalGet('node/' . $block_node->id());
    $this->assertPattern('/\d\d:\d\d:\d\d \(\d+\)/');
    $this->assertNoRaw($time);

    $this->drupalGet('admin/structure/types/manage/block_field_test/fields/node.block_field_test.field_block_field_test');
    $this->drupalPostForm(NULL, ['settings[plugin_ids][page_title_block]' => FALSE], t('Save settings'));

    $this->drupalGet('admin/structure/types/manage/block_field_test/fields/node.block_field_test.field_block_field_test');
    $this->assertResponse(200);
  }

}
