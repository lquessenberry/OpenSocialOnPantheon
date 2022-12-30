<?php

namespace Drupal\Tests\r4032login\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Test normal redirection from 403 to login page.
 *
 * @group r4032login
 */
class BaseRedirectTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['r4032login'];

  /**
   * Test the base redirection behavior.
   *
   * @param string $path
   *   Request path.
   * @param int $code
   *   Response status code.
   * @param string $destination
   *   Resulting URL.
   *
   * @dataProvider baseRedirectDataProvider
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   */
  public function testBaseRedirect($path, $code, $destination) {
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
        'admin/config',
        200,
        'user/login',
      ],
      [
        'admin/modules',
        200,
        'user/login',
      ],
    ];
  }

}
