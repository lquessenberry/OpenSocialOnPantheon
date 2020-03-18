<?php

namespace Drupal\Tests\swiftmailer\Functional;

use Drupal;
use Drupal\Core\Test\AssertMailTrait;
use Drupal\swiftmailer_test\SwiftMailerDrupalStateLogger;
use Drupal\Tests\BrowserTestBase;

/**
 * @group swiftmailer
 */
class SwiftMailerAlterTest extends BrowserTestBase {

  public static $modules = ['swiftmailer_test', 'swiftmailer', 'mailsystem'];

  public function testAlter() {
    Drupal::configFactory()
      ->getEditable('mailsystem.settings')
      ->set('modules.swiftmailer_test.none', [
        'formatter' => 'swiftmailer',
        'sender' => 'swiftmailer',
      ])
      ->save();
    Drupal::configFactory()
      ->getEditable('swiftmailer.transport')
      ->set('transport', 'null')
      ->save();
    \Drupal::state()->set('swiftmailer_test_swiftmailer_alter_1', TRUE);
    \Drupal::service('plugin.manager.mail')->mail('swiftmailer_test', 'test_1', 'test@example.com', \Drupal::languageManager()->getDefaultLanguage()->getId());
    $logger = new SwiftMailerDrupalStateLogger();
    $this->assertEquals('Replace text in swiftmailer_test_swiftmailer_alter', $logger->dump()[0]['body']);
  }

}
