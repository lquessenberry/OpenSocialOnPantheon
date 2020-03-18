<?php

namespace Drupal\Tests\r4032login\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests redirection.
 *
 * @group r4032login
 */
class RedirectTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['r4032login'];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $config = $this->config('r4032login.settings');
    $config->set('match_noredirect_pages', "/admin/config/*\n/admin/modules");
    $config->save();
  }

  /**
   * Tests match_noredirect_pages config setting.
   *
   * @param string $path
   *   Request path.
   * @param array $options
   *   Request options.
   * @param int $code
   *   Response status code.
   * @param string $destination
   *   Resulting URL.
   *
   * @dataProvider skipRedirectDataProvider
   */
  public function testSkipRedirect($path, array $options, $code, $destination) {
    $this->drupalGet($path, $options);
    $this->assertSession()->statusCodeEquals($code);
    $this->assertSession()->addressEquals($destination);
  }

  /**
   * Data provider for testSkipRedirect.
   */
  public function skipRedirectDataProvider() {
    return [
      [
        '/admin/config/development',
        [],
        403,
        '/admin/config/development',
      ],
      [
        '/admin/config',
        [],
        200,
        '/user/login',
      ],
      [
        '/admin/modules',
        [
          'query' => [
            'foo' => 'bar',
          ],
        ],
        403,
        '/admin/modules?foo=bar',
      ],
      [
        '/admin/modules/uninstall',
        [],
        200,
        '/user/login',
      ],
    ];
  }

}
