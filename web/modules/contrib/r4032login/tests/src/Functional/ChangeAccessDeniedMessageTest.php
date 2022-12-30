<?php

namespace Drupal\Tests\r4032login\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Test that it is possible to change the "User login access denied" message.
 *
 * @group r4032login
 */
class ChangeAccessDeniedMessageTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['r4032login'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $config = $this->config('r4032login.settings');
    $config->set('access_denied_message', 'my custom message');
    $config->save();
  }

  /**
   * Test that is it possible to change the "User login access denied" message.
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   */
  public function testChangeAccessDeniedMessage() {
    $this->drupalGet('admin/config');
    $this->assertSession()->elementTextContains('css', 'div[aria-label="Error message"]', 'my custom message');
  }

}
