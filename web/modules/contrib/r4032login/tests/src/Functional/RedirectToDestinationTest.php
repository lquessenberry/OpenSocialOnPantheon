<?php

namespace Drupal\Tests\r4032login\Functional;

use Drupal\Core\Url;
use Drupal\Tests\BrowserTestBase;

/**
 * Test the option "Redirect user to the page they tried to access after login".
 *
 * @group r4032login
 */
class RedirectToDestinationTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['r4032login'];

  /**
   * Tests the behavior of the redirect_to_destination option.
   *
   * @param string $loginPath
   *   The login path.
   * @param bool $optionValue
   *   The option value for "redirect_to_destination".
   *
   * @dataProvider redirectToDestinationDataProvider
   */
  public function testRedirectToDestination($loginPath, $optionValue) {
    $config = $this->config('r4032login.settings');
    $config->set('user_login_path', $loginPath);
    $config->set('redirect_to_destination', $optionValue);
    $config->save();

    $this->drupalGet('admin/config');

    $currentUrl = $this->getSession()->getCurrentUrl();
    $expectedUrl = $loginPath == '<front>'
      ? Url::fromRoute($loginPath)->toString()
      : Url::fromUserInput($loginPath)->toString();
    if ($optionValue) {
      $expectedUrl .= '?destination=' . Url::fromUserInput('/admin/config')->toString();
    }
    $expectedUrl = $this->getAbsoluteUrl($expectedUrl);

    $this->assertEquals($expectedUrl, $currentUrl);
  }

  /**
   * Data provider for testRedirectToDestination.
   */
  public function redirectToDestinationDataProvider() {
    return [
      [
        'user_login_path' => '/user/login',
        'redirect_to_destination' => TRUE,
      ],
      [
        'user_login_path' => '/user/login',
        'redirect_to_destination' => FALSE,
      ],
      [
        'user_login_path' => '<front>',
        'redirect_to_destination' => TRUE,
      ],
      [
        'user_login_path' => '<front>',
        'redirect_to_destination' => FALSE,
      ],
    ];
  }

}
