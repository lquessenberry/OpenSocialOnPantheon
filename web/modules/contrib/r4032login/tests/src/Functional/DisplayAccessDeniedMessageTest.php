<?php

namespace Drupal\Tests\r4032login\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Test that it is possible to change the "User login access denied" message.
 *
 * @group r4032login
 */
class DisplayAccessDeniedMessageTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['r4032login'];

  /**
   * Test activation and deactivation of the "User login access denied" message.
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   */
  public function testDisplayAccessDeniedMessage() {
    $config = $this->config('r4032login.settings');
    $config->set('display_denied_message', TRUE);
    $config->save();

    $this->drupalGet('admin/config');
    $this->assertSession()->elementTextContains('css', 'div[aria-label="Error message"]', 'Access denied. You must log in to view this page.');

    $config = $this->config('r4032login.settings');
    $config->set('display_denied_message', FALSE);
    $config->save();

    $this->drupalGet('admin/config');
    $this->assertSession()->elementNotExists('css', 'div[aria-label="Error message"]');
  }

}
