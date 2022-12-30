<?php

namespace Drupal\Tests\typed_data\Kernel;

use Drupal\Core\Entity\TypedData\EntityDataDefinition;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\KernelTests\KernelTestBase;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\TypedData\Exception\MissingDataException;
use Drupal\typed_data\Exception\InvalidArgumentException;

/**
 * Tests operation of the DataFetcher class.
 *
 * @group typed_data
 *
 * @coversDefaultClass \Drupal\typed_data\DataFetcher
 */
class DataFetcherTest extends KernelTestBase {

  /**
   * The typed data manager.
   *
   * @var \Drupal\Core\TypedData\TypedDataManagerInterface
   */
  protected $typedDataManager;

  /**
   * The data fetcher object we want to test.
   *
   * @var \Drupal\typed_data\DataFetcherInterface
   */
  protected $dataFetcher;

  /**
   * A node used for testing.
   *
   * @var \Drupal\node\NodeInterface
   */
  protected $node;

  /**
   * An entity type manager used for testing.
   *
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  protected $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'typed_data',
    'system',
    'node',
    'field',
    'text',
    'user',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installSchema('system', ['sequences']);

    $this->typedDataManager = $this->container->get('typed_data_manager');
    $this->dataFetcher = $this->container->get('typed_data.data_fetcher');

    $this->entityTypeManager = $this->container->get('entity_type.manager');
    $this->entityTypeManager->getStorage('node_type')
      ->create(['type' => 'page'])
      ->save();

    // Create a multi-value integer field for testing.
    FieldStorageConfig::create([
      'field_name' => 'field_integer',
      'type' => 'integer',
      'entity_type' => 'node',
      'cardinality' => FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED,
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_integer',
      'entity_type' => 'node',
      'bundle' => 'page',
    ])->save();
    $this->node = $this->entityTypeManager->getStorage('node')
      ->create([
        'title' => 'test',
        'type' => 'page',
      ]);
  }

  /**
   * @covers ::fetchDataByPropertyPath
   */
  public function testFetchingByBasicPropertyPath() {
    $this->assertEquals(
      $this->node->title->value,
      $this->dataFetcher
        ->fetchDataByPropertyPath($this->node->getTypedData(), 'title.0.value')
        ->getValue()
    );
  }

  /**
   * @covers ::fetchDataBySubPaths
   */
  public function testFetchingByBasicSubPath() {
    $this->assertEquals(
      $this->node->title->value,
      $this->dataFetcher
        ->fetchDataBySubPaths(
          $this->node->getTypedData(),
          ['title', '0', 'value']
        )
        ->getValue()
    );
  }

  /**
   * @covers ::fetchDataByPropertyPath
   */
  public function testFetchingEntityReference() {
    $user = $this->entityTypeManager->getStorage('user')
      ->create([
        'name' => 'test',
        'type' => 'user',
      ]);
    $this->node->uid->entity = $user;

    $fetched_user = $this->dataFetcher
      ->fetchDataByPropertyPath($this->node->getTypedData(), 'uid.entity')
      ->getValue();
    $this->assertSame($fetched_user, $user);
  }

  /**
   * @covers ::fetchDataByPropertyPath
   */
  public function testFetchingAcrossReferences() {
    $user = $this->entityTypeManager->getStorage('user')
      ->create([
        'name' => 'test',
        'type' => 'user',
      ]);
    $this->node->uid->entity = $user;

    $fetched_value = $this->dataFetcher
      ->fetchDataByPropertyPath($this->node->getTypedData(), 'uid.entity.name.value')
      ->getValue();
    $this->assertSame($fetched_value, 'test');
  }

  /**
   * @covers ::fetchDataByPropertyPath
   */
  public function testFetchingNonExistingEntityReference() {
    $fetched_user = $this->dataFetcher
      ->fetchDataByPropertyPath($this->node->getTypedData(), 'uid.0.entity')
      ->getValue();
    $this->assertNull($fetched_user);
  }

  /**
   * @covers ::fetchDataByPropertyPath
   */
  public function testFetchingValueAtValidPositions() {
    $this->node->field_integer->setValue(['0' => 1, '1' => 2]);

    $fetched_value = $this->dataFetcher
      ->fetchDataByPropertyPath($this->node->getTypedData(), 'field_integer.0.value')
      ->getValue();
    $this->assertEquals($fetched_value, 1);

    $fetched_value = $this->dataFetcher
      ->fetchDataByPropertyPath($this->node->getTypedData(), 'field_integer.1.value')
      ->getValue();
    $this->assertEquals($fetched_value, 2);
  }

  /**
   * @covers ::fetchDataByPropertyPath
   */
  public function testFetchingValueAtInvalidPosition() {
    $this->expectException(MissingDataException::class);
    $this->expectExceptionMessage("Unable to apply data selector 'field_integer.0.value' at 'field_integer.0'");
    $this->node->field_integer->setValue([]);

    // This should trigger an exception.
    $this->dataFetcher
      ->fetchDataByPropertyPath($this->node->getTypedData(), 'field_integer.0.value')
      ->getValue();
  }

  /**
   * @covers ::fetchDataByPropertyPath
   */
  public function testFetchingInvalidProperty() {
    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage("Unable to apply data selector 'field_invalid.0.value' at 'field_invalid'");
    // This should trigger an exception.
    $this->dataFetcher
      ->fetchDataByPropertyPath($this->node->getTypedData(), 'field_invalid.0.value')
      ->getValue();
  }

  /**
   * @covers ::fetchDataByPropertyPath
   */
  public function testFetchingEmptyProperty() {
    $this->node->field_integer->setValue([]);

    $fetched_value = $this->dataFetcher
      ->fetchDataByPropertyPath($this->node->getTypedData(), 'field_integer')
      ->getValue();
    $this->assertEquals($fetched_value, []);
  }

  /**
   * @covers ::fetchDataByPropertyPath
   */
  public function testFetchingNotExistingListItem() {
    $this->expectException(MissingDataException::class);
    $this->node->field_integer->setValue([]);

    // This will throw an exception.
    $this->dataFetcher
      ->fetchDataByPropertyPath($this->node->getTypedData(), 'field_integer.0')
      ->getValue();
  }

  /**
   * @covers ::fetchDataByPropertyPath
   */
  public function testFetchingFromEmptyData() {
    $this->expectException(MissingDataException::class);
    $this->expectExceptionMessage("Unable to apply data selector 'field_integer.0.value' at 'field_integer': Unable to get property field_integer as no entity has been provided.");
    $data_empty = $this->typedDataManager->create(EntityDataDefinition::create('node'));
    // This should trigger an exception.
    $this->dataFetcher
      ->fetchDataByPropertyPath($data_empty, 'field_integer.0.value')
      ->getValue();
  }

  /**
   * @covers ::fetchDataByPropertyPath
   */
  public function testBubbleableMetadata() {
    $this->node->field_integer->setValue([]);
    // Save the node, so that it gets an ID and it has a cache tag.
    $this->node->save();
    // Also add a user for testing cache tags of references.
    $user = $this->entityTypeManager->getStorage('user')
      ->create([
        'name' => 'test',
        'type' => 'user',
      ]);
    $user->save();
    $this->node->uid->entity = $user;

    $bubbleable_metadata = new BubbleableMetadata();
    $this->dataFetcher
      ->fetchDataByPropertyPath($this->node->getTypedData(), 'title.value', $bubbleable_metadata)
      ->getValue();

    $expected = ['node:' . $this->node->id()];
    $this->assertEquals($expected, $bubbleable_metadata->getCacheTags());

    // Test cache tags of references are added correctly.
    $this->dataFetcher
      ->fetchDataByPropertyPath($this->node->getTypedData(), 'uid.entity.name', $bubbleable_metadata)
      ->getValue();

    $expected = ['node:' . $this->node->id(), 'user:' . $user->id()];
    $this->assertEquals($expected, $bubbleable_metadata->getCacheTags());
  }

}
