<?php

namespace Drupal\Tests\r4032login\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Test the behavior of the "Add X-Robots-Tag: noindex header" option.
 *
 * @group r4032login
 */
class RedirectNoIndexHeaderTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['r4032login'];

  /**
   * Test the behavior of the "Add X-Robots-Tag: noindex header" option.
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  public function testRedirectNoIndexHeader() {
    $client = $this->getHttpClient();
    $url = $this->getAbsoluteUrl('/admin/config');

    $response = $client->request('GET', $url, [
      'allow_redirects' => FALSE,
    ]);
    $this->assertEmpty($response->getHeader('X-Robots-Tag'));

    $config = $this->config('r4032login.settings');
    $config->set('add_noindex_header', TRUE);
    $config->save();
    $response = $client->request('GET', $url, [
      'allow_redirects' => FALSE,
    ]);
    $this->assertNotEmpty($response->getHeader('X-Robots-Tag'));
    $this->assertEquals('noindex', $response->getHeader('X-Robots-Tag')[0]);
  }

}
