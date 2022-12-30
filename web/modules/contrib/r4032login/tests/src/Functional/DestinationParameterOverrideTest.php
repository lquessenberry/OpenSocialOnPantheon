<?php

namespace Drupal\Tests\r4032login\Functional;

use Drupal\Core\Url;
use Drupal\Tests\BrowserTestBase;

/**
 * Test that it is possible to override the destination parameter.
 *
 * @group r4032login
 */
class DestinationParameterOverrideTest extends BrowserTestBase {

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
    $config->set('destination_parameter_override', 'customDestination');
    $config->save();
  }

  /**
   * Test destination parameter override.
   */
  public function testDestinationParameterOverride() {
    $this->drupalGet('admin/config');

    $currentUrl = $this->getSession()->getCurrentUrl();
    $expectedUrl = $this->getAbsoluteUrl('user/login?customDestination=' . Url::fromUserInput('/admin/config')->toString());

    $this->assertEquals($expectedUrl, $currentUrl);
  }

}
