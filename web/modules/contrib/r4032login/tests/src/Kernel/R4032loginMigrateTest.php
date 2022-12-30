<?php

namespace Drupal\Tests\r4032login\Kernel;

use Drupal\Tests\migrate_drupal\Kernel\d7\MigrateDrupal7TestBase;

/**
 * Tests r4032login config migration.
 *
 * @group r4032login
 */
class R4032loginMigrateTest extends MigrateDrupal7TestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'r4032login',
  ];

  /**
   * {@inheritdoc}
   */
  protected function getFixtureFilePath() {
    return implode(DIRECTORY_SEPARATOR, [
      \Drupal::service('extension.list.module')->getPath('r4032login'),
      'tests',
      'fixtures',
      'drupal7.php',
    ]);
  }

  /**
   * Asserts that r4032login configuration is migrated.
   */
  public function testR4032loginMigration() {
    $expected_config = [
      'display_denied_message' => TRUE,
      'access_denied_message' => 'Access denied. You must log in to view this page.',
      'access_denied_message_type' => 'warning',
      'redirect_authenticated_users_to' => '<front>',
      'user_login_path' => '/user/login',
      'default_redirect_code' => 301,
      'match_noredirect_pages' => '/blog/*
/node/15
/node/16',
      'redirect_to_destination' => TRUE,
    ];
    $this->executeMigration('r4032login_settings');
    $config = $this->config('r4032login.settings')->getRawData();
    $this->assertSame($expected_config, $config);
  }

}
