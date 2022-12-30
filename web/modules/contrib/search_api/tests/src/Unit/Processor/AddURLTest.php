<?php

namespace Drupal\Tests\search_api\Unit\Processor;

use Drupal\Core\Entity\Plugin\DataType\EntityAdapter;
use Drupal\search_api\Datasource\DatasourceInterface;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\Plugin\search_api\processor\AddURL;
use Drupal\search_api\Plugin\search_api\processor\Property\AddURLProperty;
use Drupal\Tests\search_api\Unit\TestUrl;
use Drupal\Tests\UnitTestCase;

/**
 * Tests the "URL field" processor.
 *
 * @group search_api
 *
 * @see \Drupal\search_api\Plugin\search_api\processor\AddURL
 */
class AddURLTest extends UnitTestCase {

  use TestItemsTrait;

  /**
   * The path used for testing.
   */
  const TEST_PATH = '/node/example';

  /**
   * The URL used for testing.
   */
  const TEST_URL = 'http://www.example.com' . self::TEST_PATH;

  /**
   * The processor to be tested.
   *
   * @var \Drupal\search_api\Plugin\search_api\processor\AddURL
   */
  protected $processor;

  /**
   * A search index mock for the tests.
   *
   * @var \Drupal\search_api\IndexInterface
   */
  protected $index;

  /**
   * Creates a new processor object for use in the tests.
   */
  protected function setUp(): void {
    parent::setUp();

    $this->setUpMockContainer();

    // Mock the datasource of the indexer to return the mocked url object.
    $datasource = $this->createMock(DatasourceInterface::class);
    $datasource->expects($this->any())
      ->method('getItemUrl')
      ->withAnyParameters()
      ->will($this->returnValue(new TestUrl(self::TEST_PATH)));

    // Create a mock for the index to return the datasource mock.
    /** @var \Drupal\search_api\IndexInterface $index */
    $index = $this->index = $this->createMock(IndexInterface::class);
    $this->index->expects($this->any())
      ->method('getDatasource')
      ->with('entity:node')
      ->willReturn($datasource);

    // Create the tested processor and set the mocked indexer.
    $this->processor = new AddURL([], 'add_url', []);
    $this->processor->setIndex($index);
    /** @var \Drupal\Core\StringTranslation\TranslationInterface $translation */
    $translation = $this->getStringTranslationStub();
    $this->processor->setStringTranslation($translation);
  }

  /**
   * Tests whether the "URI" field is correctly filled by the processor.
   */
  public function testAddFieldValues() {
    /** @var \Drupal\node\Entity\Node $node */
    $node = $this->getMockBuilder('Drupal\node\Entity\Node')
      ->disableOriginalConstructor()
      ->getMock();

    $body_value = ['Some text value'];
    $fields = [
      'search_api_url' => [
        'type' => 'string',
      ],
      'entity:node/body' => [
        'type' => 'text',
        'values' => $body_value,
      ],
    ];
    $items = $this->createItems($this->index, 2, $fields, EntityAdapter::createFromEntity($node));

    foreach ($items as $item) {
      // Add a second URL field with "Generate absolute URL" enabled.
      $field = (clone $item->getField('url'))
        ->setFieldIdentifier('url_1')
        ->setConfiguration(['absolute' => TRUE]);
      $item->setField('url_1', $field);

      // Add the processor's field values to the items.
      $this->processor->addFieldValues($item);
    }

    // Check the generated URLs.
    $item_1 = $items[$this->itemIds[0]];
    $this->assertEquals([self::TEST_PATH], $item_1->getField('url')->getValues());
    $this->assertEquals([self::TEST_URL], $item_1->getField('url_1')->getValues());

    // Check that no other fields were changed.
    $this->assertEquals($body_value, $item_1->getField('body')->getValues());

    // Check the second item to be sure that all are processed.
    $item_2 = $items[$this->itemIds[1]];
    $this->assertEquals([self::TEST_PATH], $item_2->getField('url')->getValues());
    $this->assertEquals([self::TEST_URL], $item_2->getField('url_1')->getValues());
  }

  /**
   * Tests whether the properties are correctly altered.
   *
   * @see \Drupal\search_api\Plugin\search_api\processor\AddURL::alterPropertyDefinitions()
   */
  public function testAlterPropertyDefinitions() {
    // Check for added properties when no datasource is given.
    $properties = $this->processor->getPropertyDefinitions(NULL);
    $this->assertArrayHasKey('search_api_url', $properties);
    $this->assertInstanceOf(AddURLProperty::class, $properties['search_api_url'], 'The "search_api_url" property contains a valid data definition.');
    $this->assertEquals('string', $properties['search_api_url']->getDataType(), 'Correct data type set in the data definition.');
    $this->assertEquals('URI', $properties['search_api_url']->getLabel(), 'Correct label set in the data definition.');
    $this->assertEquals('A URI where the item can be accessed', $properties['search_api_url']->getDescription(), 'Correct description set in the data definition.');

    // Verify that there are no properties if a datasource is given.
    $datasource = $this->createMock(DatasourceInterface::class);
    $properties = $this->processor->getPropertyDefinitions($datasource);
    $this->assertEmpty($properties, 'Datasource-specific properties did not get changed.');
  }

}
