<?php

namespace Drupal\Tests\r4032login\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Test the behavior of the "HTTP redirect code" option.
 *
 * @group r4032login
 */
class RedirectStatusCodeTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['r4032login'];

  /**
   * Test the behavior of the "HTTP redirect code" option.
   *
   * @param int $optionValue
   *   The option value for the "HTTP redirect code" option.
   * @param int $expectedCode
   *   Expected redirect code.
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   *
   * @dataProvider redirectStatusCodeDataProvider
   */
  public function testRedirectStatusCode($optionValue, $expectedCode) {
    $config = $this->config('r4032login.settings');
    $config->set('default_redirect_code', $optionValue);
    $config->save();

    $client = $this->getHttpClient();
    $url = $this->getAbsoluteUrl('/admin/config');

    $response = $client->request('GET', $url, [
      'allow_redirects' => FALSE,
    ]);

    $this->assertEquals($response->getStatusCode(), $expectedCode);
  }

  /**
   * Data provider for testRedirectStatusCode.
   */
  public function redirectStatusCodeDataProvider() {
    return [
      [
        301,
        301,
      ],
      [
        302,
        302,
      ],
      [
        307,
        307,
      ],
    ];
  }

}
