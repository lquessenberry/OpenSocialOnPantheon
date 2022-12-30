<?php

namespace Drupal\Tests\r4032login\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Test authenticated redirection from 403 to configured page.
 *
 * @group r4032login
 */
class AuthenticatedRedirectTest extends BrowserTestBase {

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
   * Test the redirection behavior for authenticated user.
   *
   * @param string $optionValue
   *   The option value for "Redirect authenticated users to" option.
   * @param string $path
   *   Request path.
   * @param int $code
   *   Response status code.
   * @param string $destination
   *   Resulting URL.
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   *
   * @dataProvider baseRedirectDataProvider
   */
  public function testAuthenticatedRedirect($optionValue, $path, $code, $destination) {
    $config = $this->config('r4032login.settings');
    $config->set('redirect_authenticated_users_to', $optionValue);
    $config->save();

    $this->drupalLogin($this->webUser);

    $this->drupalGet($path);
    $this->assertSession()->statusCodeEquals($code);
    $this->assertSession()->addressEquals($destination);
  }

  /**
   * Data provider for testBaseRedirect.
   */
  public function baseRedirectDataProvider() {
    return [
      [
        '',
        'admin/config',
        403,
        'admin/config',
      ],
      [
        '/test',
        'admin/config',
        404,
        '/test',
      ],
      [
        'https://www.drupal.org',
        'admin/config',
        200,
        'https://www.drupal.org',
      ],
    ];
  }

}
