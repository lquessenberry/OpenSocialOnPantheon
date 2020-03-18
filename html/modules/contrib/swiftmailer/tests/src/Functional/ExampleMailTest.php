<?php

namespace Drupal\Tests\swiftmailer\Functional;

use Drupal\Core\Url;
use Drupal\Tests\BrowserTestBase;

/**
 * @group swiftmailer
 */
class ExampleMailTest extends BrowserTestBase {

  public static $modules = ['swiftmailer', 'mailsystem'];

  /**
   * Tests the e-mail test form.
   */
  public function testForm() {
    $account = $this->createUser(['administer swiftmailer']);
    $this->drupalLogin($account);
    $this->drupalPostForm(Url::fromRoute('swiftmailer.test'), [], 'Send');
    $this->assertSession()->pageTextContains(t('An attempt has been made to send an e-mail to @email.', ['@email' => $account->getEmail()]));
  }

}
