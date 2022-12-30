<?php

namespace Drupal\Tests\r4032login\Functional;

use Drupal\Core\Url;
use Drupal\Tests\BrowserTestBase;

/**
 * Test that query string is preserved after redirection.
 *
 * @group r4032login
 */
class PreserveQueryStringTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['r4032login'];

  /**
   * Tests query string preservation.
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   */
  public function testPreserveQueryString() {
    $this->drupalGet('admin/modules', [
      'query' => [
        'foo' => 'bar',
      ],
    ]);

    $currentUrl = $this->getSession()->getCurrentUrl();
    $expectedUrl = $this->getAbsoluteUrl('user/login?destination=' . Url::fromUserInput('/admin/modules')->toString() . '%3Ffoo%3Dbar');

    $this->assertEquals($expectedUrl, $currentUrl);
  }

}
