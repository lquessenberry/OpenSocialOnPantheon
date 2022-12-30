<?php

namespace Drupal\Tests\search_api\Unit\Processor;

use Drupal\Component\Transliteration\TransliterationInterface;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\Plugin\search_api\processor\Transliteration;
use Drupal\Tests\UnitTestCase;

/**
 * Tests the "Transliteration" processor.
 *
 * @group search_api
 *
 * @see \Drupal\search_api\Plugin\search_api\processor\Transliteration
 */
class TransliterationTest extends UnitTestCase {

  use ProcessorTestTrait, TestItemsTrait;

  /**
   * A test index mock to use for tests.
   *
   * @var \Drupal\search_api\IndexInterface
   */
  protected $index;

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();

    $this->index = $this->createMock(IndexInterface::class);

    $this->setUpMockContainer();
    $this->processor = new Transliteration([], 'transliteration', []);
    $this->processor->setLangcode('en');

    $transliterator = $this->createMock(TransliterationInterface::class);
    $transliterate = function ($string, $langcode = 'en', $unknown_character = '?', $max_length = NULL) {
      return "translit-$string-$langcode$unknown_character$max_length";
    };
    $transliterator->expects($this->any())
      ->method('transliterate')
      ->will($this->returnCallback($transliterate));
    /** @var \Drupal\Component\Transliteration\TransliterationInterface $transliterator */
    $this->processor->setTransliterator($transliterator);
  }

  /**
   * Tests that integers are not affected.
   */
  public function testTransliterationWithInteger() {
    $field_value = 5;
    /** @var \Drupal\search_api\Item\FieldInterface $field */
    $items = $this->createSingleFieldItem($this->index, 'int', $field_value, $field);
    $this->processor->preprocessIndexItems($items);
    $this->assertEquals([$field_value], $field->getValues(), 'Integer not affected by transliteration.');
  }

  /**
   * Tests that floating point numbers are not affected.
   */
  public function testTransliterationWithDouble() {
    $field_value = 3.14;
    /** @var \Drupal\search_api\Item\FieldInterface $field */
    $items = $this->createSingleFieldItem($this->index, 'double', $field_value, $field);
    $this->processor->preprocessIndexItems($items);
    $this->assertEquals([$field_value], $field->getValues(), 'Floating point number not affected by transliteration.');
  }

  /**
   * Tests that strings are affected.
   */
  public function testTransliterationWithString() {
    $field_value = 'test_string';
    /** @var \Drupal\search_api\Item\FieldInterface $field */
    $items = $this->createSingleFieldItem($this->index, 'string', $field_value, $field);
    $this->processor->preprocessIndexItems($items);
    $expected_value = "translit-$field_value-en?";
    $this->assertEquals([$expected_value], $field->getValues(), 'Strings are correctly transliterated.');
  }

}
