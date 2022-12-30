<?php

namespace Drupal\Tests\search_api\Unit;

use Drupal\search_api\Plugin\search_api\data_type\DateDataType;
use Drupal\Tests\UnitTestCase;
use Psr\Log\LoggerInterface;

/**
 * Tests functionality of the "Date" data type plugin.
 *
 * @coversDefaultClass \Drupal\search_api\Plugin\search_api\data_type\DateDataType
 *
 * @group search_api
 */
class DateDataTypeTest extends UnitTestCase {

  /**
   * The data type plugin to test.
   *
   * @var \Drupal\search_api\Plugin\search_api\data_type\DateDataType
   */
  protected $plugin;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Make sure the default timezone isn't UTC.
    date_default_timezone_set('America/Chicago');

    $this->plugin = new DateDataType([], '', []);
  }

  /**
   * Tests value conversion.
   *
   * @param mixed $value
   *   The incoming value.
   * @param int $expected
   *   The expected converted value.
   *
   * @dataProvider getValueTestDataProvider
   */
  public function testGetValue($value, $expected) {
    $value = $this->plugin->getValue($value);
    $this->assertSame($expected, $value);
  }

  /**
   * Provides test data for testGetValue().
   *
   * @return array[]
   *   An array of argument arrays for testGetValue().
   *
   * @see \Drupal\Tests\search_api\Unit\DateDataTypeTest::testGetValue()
   */
  public function getValueTestDataProvider() {
    $t = 1400000000;
    $f = 'Y-m-d H:i:s';
    return [
      'timestamp' => [$t, $t],
      'string timestamp' => ["$t", $t],
      'float timestamp' => [$t + 0.12, $t],
      'date string' => [gmdate($f, $t), $t],
      'date string with timezone' => [date($f . 'P', $t), $t],
    ];
  }

  /**
   * Tests that an invalid date value is handled correctly.
   */
  public function testInvalidValue(): void {
    $logger = $this->createMock(LoggerInterface::class);
    $logged = [];
    $callback = function (string $message, array $context = []) use (&$logged) {
      $logged[] = strtr($message, $context);
    };
    $logger->method('warning')->willReturnCallback($callback);
    $this->plugin->setLogger($logger);

    $value = $this->plugin->getValue('foobar');
    $this->assertNull($value);
    $this->assertEquals(['Error while parsing date/time value "foobar": The timezone could not be found in the database.'], $logged);
  }

}
