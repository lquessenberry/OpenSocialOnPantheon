<?php

namespace Drupal\Tests\r4032login\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Test authenticated redirection from 403 to configured page.
 *
 * @group r4032login
 */
class AuthenticatedNotFoundOptionTest extends BrowserTestBase {

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
   * Test the not found option for authenticated user.
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   */
  public function testAuthenticatedRedirect() {
    $config = $this->config('r4032login.settings');
    $config->set('throw_authenticated_404', TRUE);
    $config->save();

    $this->drupalLogin($this->webUser);

    // Test a 404 is thrown is the option is set to TRUE.
    $this->drupalGet('admin/config');
    $this->assertSession()->statusCodeEquals(404);
    $this->assertSession()->addressEquals('admin/config');

    // Test a 404 is thrown even if a redirected destination is set for
    // authenticated users when the option is set to TRUE.
    $config->set('redirect_authenticated_users_to', 'https://www.drupal.org');
    $this->drupalGet('admin/config');
    $this->assertSession()->statusCodeEquals(404);
    $this->assertSession()->addressEquals('admin/config');
  }

}
