<?php

namespace Drupal\Tests\swiftmailer\Kernel\Utility;

use Drupal\KernelTests\KernelTestBase;
use Drupal\swiftmailer\Utility\Conversion;

/**
 * @coversDefaultClass \Drupal\swiftmailer\Utility\Conversion
 * @group swiftmailer
 */
class ConversionTest extends KernelTestBase {

  /**
   * @dataProvider swiftmailer_parse_mailboxes_dataProvider
   */
  public function test_swiftmailer_parse_mailboxes($value, $expected) {
    $this->assertSame($expected, Conversion::swiftmailer_parse_mailboxes($value));
  }

  /**
   * DataProvider for ::test_swiftmailer_parse_mailboxes.
   */
  public function swiftmailer_parse_mailboxes_dataProvider() {
    return [
      [
        'mail@example.com',
        [
          'mail@example.com',
        ],
      ],
      [
        'mail1@example.com;mail2@example.com',
        [
          'mail1@example.com',
          'mail2@example.com',
        ],
      ],
      [
        'mail1@example.com;invalid-email',
        [
          'mail1@example.com',
        ],
      ],
    ];
  }

}
