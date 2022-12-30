<?php

namespace Drupal\Tests\paragraphs\Functional\Migrate;

use Drupal\Tests\paragraphs\Traits\ParagraphsNodeMigrationAssertionsTrait;

/**
 * Tests the migration of paragraph entities.
 *
 * @group paragraphs
 *
 * @group legacy
 */
class MigrateUiParagraphsTest extends MigrateUiParagraphsTestBase {

  use ParagraphsNodeMigrationAssertionsTrait;

  /**
   * Tests the result of the paragraphs migration.
   *
   * @dataProvider providerParagraphsMigrate
   */
  public function testParagraphsMigrate($node_migrate_type_classic) {
    // Drupal 8.8.x only has 'classic' node migrations.
    // @see https://www.drupal.org/node/3105503
    if (!$node_migrate_type_classic && version_compare(\Drupal::VERSION, '8.9', '<')) {
      $this->pass("Drupal 8.8.x has only the 'classic' node migration.");
      return;
    }
    $this->setClassicNodeMigration($node_migrate_type_classic);
    $this->assertMigrateUpgradeViaUi();
    $this->assertParagraphsMigrationResults();
    $this->assertNode8Paragraphs();
    $this->assertNode9Paragraphs();
    $this->assertIcelandicNode9Paragraphs();
  }

  /**
   * Provides data and expected results for testing paragraph migrations.
   *
   * @return bool[][]
   *   Classic node migration type.
   */
  public function providerParagraphsMigrate() {
    return [
      ['node_migrate_type_classic' => TRUE],
      ['node_migrate_type_classic' => FALSE],
    ];
  }

}
