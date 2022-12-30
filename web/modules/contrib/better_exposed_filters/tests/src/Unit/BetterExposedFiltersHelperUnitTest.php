<?php

namespace Drupal\Tests\better_exposed_filters\Unit;

use Drupal\better_exposed_filters\BetterExposedFiltersHelper;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Tests\UnitTestCase;

/**
 * Tests the helper functions for better exposed filters.
 *
 * @coversDefaultClass \Drupal\better_exposed_filters\BetterExposedFiltersHelper
 *
 * @group better_exposed_filters
 */
class BetterExposedFiltersHelperUnitTest extends UnitTestCase {

  use StringTranslationTrait;

  /**
   * Tests options are rewritten correctly.
   *
   * @dataProvider providerTestRewriteOptions
   *
   * @covers ::rewriteOptions
   */
  public function testRewriteOptions($options, $settings, $expected) {
    $actual = BetterExposedFiltersHelper::rewriteOptions($options, $settings);
    $this->assertEquals(array_values($expected), array_values($actual));
  }

  /**
   * Data provider for ::testRewriteOptions.
   */
  public function providerTestRewriteOptions() {
    $data = [];

    // Super basic rewrite.
    $data[] = [
      ['foo' => 'bar'],
      "bar|baz",
      ['foo' => 'baz'],
    ];

    // Removes an option.
    $data[] = [
      ['foo' => 'bar'],
      "bar|",
      [],
    ];

    // An option in the middle is removed -- preserves order.
    $data[] = [
      ['foo' => '1', 'bar' => '2', 'baz' => '3'],
      "2|",
      ['foo' => '1', 'baz' => '3'],
    ];

    // Ensure order is preserved.
    $data[] = [
      ['foo' => '1', 'bar' => '2', 'baz' => '3'],
      "2|Two",
      ['foo' => '1', 'bar' => 'Two', 'baz' => '3'],
    ];

    // No options are replaced.
    $data[] = [
      ['foo' => '1', 'bar' => '2', 'baz' => '3'],
      "4|Two",
      ['foo' => '1', 'bar' => '2', 'baz' => '3'],
    ];

    // All options are replaced.
    $data[] = [
      ['foo' => '1', 'bar' => '2', 'baz' => '3'],
      "1|One\n2|Two\n3|Three",
      ['foo' => 'One', 'bar' => 'Two', 'baz' => 'Three'],
    ];

    return $data;
  }

  /**
   * Tests options are rewritten correctly.
   *
   * @dataProvider providerTestRewriteReorderOptions
   *
   * @covers ::rewriteOptions
   */
  public function testRewriteReorderOptions($options, $settings, $expected) {
    $actual = BetterExposedFiltersHelper::rewriteOptions($options, $settings, TRUE);
    $this->assertEquals(array_values($expected), array_values($actual));
  }

  /**
   * Data provider for ::testRewriteReorderOptions.
   */
  public function providerTestRewriteReorderOptions() {
    $data = [];

    // Basic use case.
    $data[] = [
      ['foo' => '1', 'bar' => '2', 'baz' => '3'],
      '2|Two',
      ['bar' => 'Two', 'foo' => '1', 'baz' => '3'],
    ];

    // No option replaced should not change the order.
    $data[] = [
      ['foo' => '1', 'bar' => '2', 'baz' => '3'],
      '4|Four',
      ['foo' => '1', 'bar' => '2', 'baz' => '3'],
    ];

    // Completely reorder options.
    $data[] = [
      ['foo' => '1', 'bar' => '2', 'baz' => '3'],
      "3|Three\n2|Two\n1|One",
      ['baz' => 'Three', 'bar' => 'Two', 'foo' => 'One'],
    ];

    return $data;
  }

  /**
   * Tests options are rewritten correctly.
   *
   * @dataProvider providerTestRewriteTaxonomy
   *
   * @covers ::rewriteOptions
   */
  public function testRewriteTaxonomy($options, $settings, $expected, $reorder = FALSE) {
    $actual = BetterExposedFiltersHelper::rewriteOptions($options, $settings, $reorder);
    $this->assertEquals(array_values($expected), array_values($actual));
  }

  /**
   * Data provider for ::testRewriteTaxonomy.
   */
  public function providerTestRewriteTaxonomy() {
    $data = [];

    // Replace a single item, no change in order.
    $data[] = [
      [
        (object) ['option' => [123 => 'term1']],
        (object) ['option' => [456 => 'term2']],
        (object) ['option' => [789 => 'term3']],
      ],
      "term2|Two",
      [
        (object) ['option' => [123 => 'term1']],
        (object) ['option' => [456 => 'Two']],
        (object) ['option' => [789 => 'term3']],
      ],
    ];

    // Replace all items, no change in order.
    $data[] = [
      [
        (object) ['option' => [123 => 'term1']],
        (object) ['option' => [456 => 'term2']],
        (object) ['option' => [789 => 'term3']],
      ],
      "term2|Two\nterm3|Three\nterm1|One",
      [
        (object) ['option' => [123 => 'One']],
        (object) ['option' => [456 => 'Two']],
        (object) ['option' => [789 => 'Three']],
      ],
    ];

    // Replace a single item, with change in order.
    $data[] = [
     [
       (object) ['option' => [123 => 'term1']],
       (object) ['option' => [456 => 'term2']],
       (object) ['option' => [789 => 'term3']],
     ], "term2|Two",
     [
       (object) ['option' => [456 => 'Two']],
       (object) ['option' => [123 => 'term1']],
       (object) ['option' => [789 => 'term3']],
     ], TRUE,
    ];

    // Replace all items, with change in order.
    $data[] = [
     [
       (object) ['option' => [123 => 'term1']],
       (object) ['option' => [456 => 'term2']],
       (object) ['option' => [789 => 'term3']],
     ], "term2|Two\nterm3|Three\nterm1|One",
     [
       (object) ['option' => [456 => 'Two']],
       (object) ['option' => [789 => 'Three']],
       (object) ['option' => [123 => 'One']],
     ], TRUE,
    ];

    return $data;
  }

  /**
   * Tests options are rewritten correctly.
   *
   * @dataProvider providerTestSortOptions
   *
   * @covers ::sortOptions
   */
  public function testSortOptions($unsorted, $expected) {
    $sorted = BetterExposedFiltersHelper::sortOptions($unsorted);
    $this->assertEquals(array_values($sorted), array_values($expected));
  }

  /**
   * Data provider for ::testSortOptions.
   */
  public function providerTestSortOptions() {
    // Data providers run before ::setUp. We rely on the stringTranslationTrait
    // for some of our option values so call it here instead.
    $this->stringTranslation = $this->getStringTranslationStub();

    $data = [];

    // List of strings.
    $data[] = [
      [
        'e',
        'a',
        'b',
        'd',
        'c',
      ], [
        'a',
        'b',
        'c',
        'd',
        'e',
      ],
    ];

    // List of mixed values.
    $data[] = [
      [
        '1',
        'a',
        '2',
        'b',
        '3',
      ], [
        '1',
        '2',
        '3',
        'a',
        'b',
      ],
    ];

    // List of taxonomy terms.
    $data[] = [
      [
        (object) ['option' => [555 => 'term5']],
        (object) ['option' => [222 => 'term2']],
        (object) ['option' => [444 => 'term4']],
        (object) ['option' => [333 => 'term3']],
        (object) ['option' => [111 => 'term1']],
      ], [
        (object) ['option' => [111 => 'term1']],
        (object) ['option' => [222 => 'term2']],
        (object) ['option' => [333 => 'term3']],
        (object) ['option' => [444 => 'term4']],
        (object) ['option' => [555 => 'term5']],
      ],
    ];

    return $data;
  }

  /**
   * Tests options are rewritten correctly.
   *
   * @dataProvider providerTestSortNestedOptions
   *
   * @covers ::sortNestedOptions
   */
  public function testSortNestedOptions($unsorted, $expected) {
    $sorted = BetterExposedFiltersHelper::sortNestedOptions($unsorted);
    $this->assertEquals(array_values($sorted), array_values($expected));
  }

  /**
   * Data provider for ::testSortNestedOptions.
   */
  public function providerTestSortNestedOptions() {
    // Data providers run before ::setUp. We rely on the stringTranslationTrait
    // for some of our option values so call it here instead.
    $this->stringTranslation = $this->getStringTranslationStub();

    $data = [];

    // List of nested taxonomy terms.
    $data[] = [
      [
        (object) ['option' => [2303 => 'United States']],
        (object) ['option' => [2311 => '-Washington']],
        (object) ['option' => [2312 => '--Seattle']],
        (object) ['option' => [2313 => '--Spokane']],
        (object) ['option' => [2314 => '--Walla Walla']],
        (object) ['option' => [2304 => '-California']],
        (object) ['option' => [2307 => '--Santa Barbara']],
        (object) ['option' => [2306 => '--San Diego']],
        (object) ['option' => [2305 => '--San Francisco']],
        (object) ['option' => [2308 => '-Oregon']],
        (object) ['option' => [2310 => '--Eugene']],
        (object) ['option' => [2309 => '--Portland']],
        (object) ['option' => [2324 => 'Mexico']],
        (object) ['option' => [2315 => 'Canada']],
        (object) ['option' => [2316 => '-British Columbia']],
        (object) ['option' => [2319 => '--Whistler']],
        (object) ['option' => [2317 => '--Vancouver']],
        (object) ['option' => [2318 => '--Victoria']],
        (object) ['option' => [2320 => '-Alberta']],
        (object) ['option' => [2321 => '--Calgary']],
        (object) ['option' => [2323 => '--Lake Louise']],
        (object) ['option' => [2322 => '--Edmonton']],
      ], [
        (object) ['option' => [2315 => 'Canada']],
        (object) ['option' => [2320 => '-Alberta']],
        (object) ['option' => [2321 => '--Calgary']],
        (object) ['option' => [2322 => '--Edmonton']],
        (object) ['option' => [2323 => '--Lake Louise']],
        (object) ['option' => [2316 => '-British Columbia']],
        (object) ['option' => [2317 => '--Vancouver']],
        (object) ['option' => [2318 => '--Victoria']],
        (object) ['option' => [2319 => '--Whistler']],
        (object) ['option' => [2324 => 'Mexico']],
        (object) ['option' => [2303 => 'United States']],
        (object) ['option' => [2304 => '-California']],
        (object) ['option' => [2306 => '--San Diego']],
        (object) ['option' => [2305 => '--San Francisco']],
        (object) ['option' => [2307 => '--Santa Barbara']],
        (object) ['option' => [2308 => '-Oregon']],
        (object) ['option' => [2310 => '--Eugene']],
        (object) ['option' => [2309 => '--Portland']],
        (object) ['option' => [2311 => '-Washington']],
        (object) ['option' => [2312 => '--Seattle']],
        (object) ['option' => [2313 => '--Spokane']],
        (object) ['option' => [2314 => '--Walla Walla']],
      ],
    ];

    return $data;
  }

}
