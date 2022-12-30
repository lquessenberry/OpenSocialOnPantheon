<?php

namespace Drupal\Tests\r4032login\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Test authenticated redirection includes a custom message.
 *
 * @group r4032login
 */
class AuthenticatedRedirectCustomMessageTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['r4032login'];

  /**
   * A regular user.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $webUser;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->webUser = $this->drupalCreateUser();
  }

  /**
   * Test message override behavior or redirection of authenticated users.
   *
   * @param string $message
   *   The message to set in config and to look for after redirecting.
   * @param string $message_type
   *   The type of message to show.
   * @param string $selector
   *   The selector to look for the message within.
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   *
   * @dataProvider baseRedirectMessageDataProvider
   */
  public function testAuthenticatedRedirectMessage($message, $message_type, $selector) {
    $config = $this->config('r4032login.settings');
    $config->set('access_denied_auth_message', $message);
    $config->set('access_denied_auth_message_type', $message_type);
    $config->set('redirect_authenticated_users_to', '/');
    $config->save();

    $this->drupalLogin($this->webUser);

    $this->drupalGet('admin/config');
    $this->assertSession()->elementContains('css', $selector, $message);
  }

  /**
   * Data provider for testAuthenticatedRedirectMessage.
   */
  public function baseRedirectMessageDataProvider() {
    return [
      [
        'You are not supposed to be here.',
        'error',
        'div[aria-label="Error message"]',
      ],
      [
        'You are not supposed to be <p>here</p>.',
        'warning',
        'div[aria-label="Warning message"]',
      ],
      [
        'You are not supposed to be here.',
        'status',
        'div[aria-label="Status message"]',
      ],
    ];
  }

  /**
   * Test authenticated message will not appear if disabled.
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   */
  public function testDisabledAuthenticatedRedirectMessage() {
    $config = $this->config('r4032login.settings');
    $config->set('display_auth_denied_message', FALSE);
    $config->save();

    $this->drupalLogin($this->webUser);

    $this->drupalGet('admin/config');
    $this->assertSession()->elementNotExists('css', 'div[aria-label="Error message"]');
  }

}
