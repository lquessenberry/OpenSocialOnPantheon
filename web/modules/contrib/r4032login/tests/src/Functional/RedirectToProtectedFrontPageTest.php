<?php

namespace Drupal\Tests\r4032login\Functional;

use Drupal\Core\Url;
use Drupal\Tests\BrowserTestBase;

/**
 * Test the redirection to a protected front page.
 *
 * @group r4032login
 */
class RedirectToProtectedFrontPageTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'r4032login',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Set the front page as a protected page.
    $config = $this->config('system.site');
    $config->set('page.front', '/admin');
    $config->save();
  }

  /**
   * Test the redirection.
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   */
  public function testRedirectToProtectedFrontPage() {
    // Assert there is the redirection since the node is not published.
    $this->drupalGet('<front>');

    $this->assertEquals($this->getAbsoluteUrl('user/login?destination=' . Url::fromUserInput('/admin')->toString()), $this->getUrl());
  }

}
