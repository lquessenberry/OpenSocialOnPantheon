<?php

namespace Drupal\Tests\r4032login\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Test that is well avoided to redirect for configured urls.
 *
 * @group r4032login
 */
class SkipRedirectTest extends BrowserTestBase {

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
    $config->set('match_noredirect_pages', "/admin/config/*\n/admin/modules");
    $config->save();
  }

  /**
   * Test that is well avoided to redirect for configured urls.
   *
   * @param string $path
   *   Request path.
   * @param int $code
   *   Response status code.
   * @param string $destination
   *   Resulting URL.
   * @param int $negate
   *   Negate the skip redirection condition.
   *
   * @dataProvider skipRedirectDataProvider
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   */
  public function testSkipRedirect($path, $code, $destination, $negate) {
    $this->config('r4032login.settings')->set('match_noredirect_negate', $negate)->save();

    $this->drupalGet($path);
    $this->assertSession()->statusCodeEquals($code);
    $this->assertSession()->addressEquals($destination);
  }

  /**
   * Data provider for testSkipRedirect.
   */
  public function skipRedirectDataProvider() {
    return [
      [
        'admin/config/development',
        403,
        'admin/config/development',
        0,
      ],
      [
        'admin/config/development',
        200,
        'user/login',
        1,
      ],
      [
        'admin/config',
        200,
        'user/login',
        0,
      ],
      [
        'admin/config',
        403,
        'admin/config',
        1,
      ],
      [
        'admin/modules',
        403,
        'admin/modules',
        0,
      ],
      [
        'admin/modules',
        200,
        'user/login',
        1,
      ],
      [
        'admin/modules/uninstall',
        200,
        'user/login',
        0,
      ],
      [
        'admin/modules/uninstall',
        403,
        'admin/modules/uninstall',
        1,
      ],
    ];
  }

}
