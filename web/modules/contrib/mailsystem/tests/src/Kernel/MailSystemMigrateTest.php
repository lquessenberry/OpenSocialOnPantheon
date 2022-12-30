<?php

namespace Drupal\Tests\mailsystem\Kernel\d7;

use Drupal\Tests\migrate_drupal\Kernel\d7\MigrateDrupal7TestBase;

/**
 * Tests mail system migration.
 *
 * @group mailsystem
 */
class MailSystemMigrateTest extends MigrateDrupal7TestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'color',
    'mailsystem',
  ];

  /**
   * {@inheritdoc}
   */
  protected function getFixtureFilePath() {
    return implode(DIRECTORY_SEPARATOR, [
      \Drupal::service('extension.list.module')->getPath('mailsystem'),
      'tests',
      'fixtures',
      'drupal7.php',
    ]);
  }

  /**
   * Asserts that mail system configuration is migrated.
   */
  public function testMailSystemMigration() {
    $expected_config = [
      'theme' => 'default',
      'defaults' => [
        'sender' => 'test_mail_collector',
        'formatter' => 'test_mail_collector',
      ],
      'modules' => [
        'color' => [
          '1111' => [
            'formatter' => 'php_mail',
            'sender' => 'php_mail',
          ],
        ],
        'system' => [
          '123' => [
            'formatter' => 'test_mail_collector',
            'sender' => 'test_mail_collector',
          ],
        ],
      ],
    ];
    $this->executeMigration('mail_system_settings');
    $config_after = $this->config('mailsystem.settings')->getRawData();
    $this->assertSame($expected_config, $config_after);

  }

}
