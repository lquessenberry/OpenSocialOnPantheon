<?php

namespace Drupal\Tests\r4032login\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Test that it is possible to change the path to the user login form.
 *
 * @group r4032login
 */
class ChangeLoginFormPathTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['r4032login'];

  /**
   * Test user login form path option modification.
   *
   * @param bool $optionValue
   *   The option value for "user_login_path".
   * @param string $path
   *   Request path.
   * @param string $destination
   *   Resulting URL.
   *
   * @dataProvider changeLoginFormPathDataProvider
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   */
  public function testChangeLoginFormPath($optionValue, $path, $destination) {
    $config = $this->config('r4032login.settings');
    $config->set('user_login_path', $optionValue);
    $config->save();

    $this->drupalGet($path);
    $this->assertSession()->addressEquals($destination);
  }

  /**
   * Data provider for testChangeLoginFormPath.
   */
  public function changeLoginFormPathDataProvider() {
    return [
      [
        '/user/customLogin',
        'admin/config',
        'user/customLogin',
      ],
      [
        'https://www.drupal.org/user/login',
        'admin/config',
        'https://www.drupal.org/user/login',
      ],
    ];
  }

}
