<?php

namespace Drupal\flag\Tests;

use Drupal\Core\Database\Database;

/**
 * Tests uninstalling the Flag module.
 *
 * @group flag
 */
class FlagUninstallTest extends FlagTestBase {

  /**
   * @var array
   */
  protected $flags = [];

  /**
   * @var array
   */
  protected $nodes = [];

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();

    // Setup an admin user.
    $admin_user = $this->createUser([
      'administer flags',
      'administer flagging display',
      'administer node display',
      'administer modules',
    ]);

    // Login.
    $this->drupalLogin($admin_user);

    // Create some flags.
    for ($i = 0; $i < 2; $i++) {
      $flag = $this->createFlag('node', ['article'], 'reload');;
      $this->grantFlagPermissions($flag);
      $this->flags[$i] = $flag;
    }

    // Create some nodes.
    for ($i = 0; $i < 3; $i++) {
      $this->nodes[$i] = $this->drupalCreateNode(['type' => 'article']);
    }

    // Flag some nodes.
    $this->flagService->flag($this->flags[0], $this->nodes[1]);
    $this->flagService->flag($this->flags[0], $this->nodes[2]);
    $this->flagService->flag($this->flags[1], $this->nodes[2]);
  }

  /**
   * Tests uninstalling the module.
   */
  public function testUninstall() {
    // Verify we have flags.
    $this->drupalGet('admin/structure/flags');
    $this->assertNoText($this->t('There is no Flag yet.'));
    $this->checkCountsExist();

    // Verify that the uninstall form exists.
    $this->drupalGet('admin/structure/flags/clear');
    $this->assertText($this->t('Clear all flag data?'));

    // Delete Flag data.
    $this->drupalPostForm(NULL, [], $this->t('Clear all'));
    $this->assertText($this->t('Flag data has been cleared.'));
    $this->checkNoCountsExist();

    // Uninstall module.
    $this->drupalPostForm('admin/modules/uninstall', ['uninstall[flag]' => TRUE], $this->t('Uninstall'));
    $this->drupalPostForm(NULL, [], t('Uninstall'));

    // Validate Flag was uninstalled.
    $this->assertText($this->t('Flag has been uninstalled.'));
    $this->assertText($this->t('The selected modules have been uninstalled.'));
    $this->drupalGet('admin/structure/flags');
    $this->assertResponse(404);
  }

  /**
   * Checks if the flag_counts table is not empty using a direct query.
   */
  protected function checkCountsExist() {
    // Get the database connection.
    $connection = Database::getConnection();

    // Query the table for counts.
    $result = $connection->select('flag_counts', 'fc')
      ->fields('fc', ['flag_id', 'count'])
      ->countQuery()
      ->execute()
      ->fetchField();

    $this->assertNotEqual($result, 0, 'The flag_counts table is not empty.');
  }

  /**
   * Checks if the flag_counts table is  empty using a direct query.
   */
  protected function checkNoCountsExist() {
    // Get the database connection.
    $connection = Database::getConnection();

    // Query the table for counts.
    $result = $connection->select('flag_counts', 'fc')
      ->fields('fc', ['flag_id', 'count'])
      ->countQuery()
      ->execute()
      ->fetchField();

    $this->assertEqual($result, 0, 'The flag_counts table is empty.');
  }
}
