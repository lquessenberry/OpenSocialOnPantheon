<?php

namespace Drupal\Tests\r4032login\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Test that it is possible to change the type of the "Access denied" message.
 *
 * @group r4032login
 */
class ChangeMessageTypeTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['r4032login'];

  /**
   * Test type modification of the "User login access denied" message.
   *
   * @param string $optionValue
   *   The option value to set.
   * @param string $selector
   *   The selector we need to find on the page.
   *
   * @dataProvider changeMessageTypeDataProvider
   *
   * @throws \Behat\Mink\Exception\ElementNotFoundException
   */
  public function testChangeMessageType($optionValue, $selector) {
    $config = $this->config('r4032login.settings');
    $config->set('access_denied_message_type', $optionValue);
    $config->save();

    $this->drupalGet('admin/config');
    $this->assertSession()->elementExists('css', $selector);
  }

  /**
   * Data provider for testChangeMessageType.
   */
  public function changeMessageTypeDataProvider() {
    return [
      [
        'error',
        'div[aria-label="Error message"]',
      ],
      [
        'warning',
        'div[aria-label="Warning message"]',
      ],
      [
        'status',
        'div[aria-label="Status message"]',
      ],
    ];
  }

}
