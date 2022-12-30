<?php

namespace Drupal\Tests\typed_data\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\typed_data\Exception\InvalidArgumentException;

/**
 * Tests that data fetcher definition fetching functions work correctly.
 *
 * @coversDefaultClass \Drupal\typed_data\DataFetcher
 *
 * @group typed_data
 */
class DataDefinitionFetcherTest extends KernelTestBase {

  /**
   * The data fetcher object we want to test.
   *
   * @var \Drupal\typed_data\DataFetcherInterface
   */
  protected $dataFetcher;

  /**
   * The typed data manager.
   *
   * @var \Drupal\Core\TypedData\TypedDataManagerInterface
   */
  protected $typedDataManager;

  /**
   * The data definition of our page node used for testing.
   *
   * @var \Drupal\Core\Entity\TypedData\EntityDataDefinitionInterface
   */
  protected $nodeDefinition;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['typed_data', 'system', 'node', 'field', 'user'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('node');

    $this->dataFetcher = $this->container->get('typed_data.data_fetcher');
    $this->typedDataManager = $this->container->get('typed_data_manager');

    $entity_type_manager = $this->container->get('entity_type.manager');
    $entity_type_manager->getStorage('node_type')
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

    $node = $entity_type_manager->getStorage('node')
      ->create([
        'title' => 'test',
        'type' => 'page',
      ]);
    $this->nodeDefinition = $node->getTypedData()->getDataDefinition();
  }

  /**
   * @covers ::fetchDefinitionByPropertyPath
   */
  public function testFetchingByBasicPropertyPath() {
    $target_definition = $this->nodeDefinition
      ->getPropertyDefinition('title')
      ->getItemDefinition()
      ->getPropertyDefinition('value');

    $fetched_definition = $this->dataFetcher->fetchDefinitionByPropertyPath(
      $this->nodeDefinition,
      'title.0.value'
    );

    $this->assertSame($target_definition, $fetched_definition);
  }

  /**
   * @covers ::fetchDefinitionBySubPaths
   */
  public function testFetchingByBasicSubPath() {
    $target_definition = $this->nodeDefinition
      ->getPropertyDefinition('title')
      ->getItemDefinition()
      ->getPropertyDefinition('value');

    $fetched_definition = $this->dataFetcher->fetchDefinitionBySubPaths(
      $this->nodeDefinition,
      ['title', '0', 'value']
    );

    $this->assertSame($target_definition, $fetched_definition);
  }

  /**
   * @covers ::fetchDefinitionByPropertyPath
   */
  public function testFetchingEntityReference() {
    $target_definition = $this->nodeDefinition
      ->getPropertyDefinition('uid')
      ->getItemDefinition()
      ->getPropertyDefinition('entity');

    $fetched_definition = $this->dataFetcher->fetchDefinitionByPropertyPath(
      $this->nodeDefinition,
      'uid.entity'
    );

    $this->assertSame($target_definition, $fetched_definition);
  }

  /**
   * @covers ::fetchDefinitionByPropertyPath
   */
  public function testFetchingAcrossReferences() {
    $target_definition = $this->nodeDefinition
      ->getPropertyDefinition('uid')
      ->getItemDefinition()
      ->getPropertyDefinition('entity')
      ->getTargetDefinition()
      ->getPropertyDefinition('name')
      ->getItemDefinition()
      ->getPropertyDefinition('value');

    $fetched_definition = $this->dataFetcher->fetchDefinitionByPropertyPath(
      $this->nodeDefinition,
      'uid.entity.name.value'
    );

    $this->assertSame($target_definition, $fetched_definition);
  }

  /**
   * @covers ::fetchDefinitionByPropertyPath
   */
  public function testFetchingAtValidPositions() {
    $target_definition = $this->nodeDefinition
      ->getPropertyDefinition('field_integer')
      ->getItemDefinition()
      ->getPropertyDefinition('value');

    $fetched_definition = $this->dataFetcher->fetchDefinitionByPropertyPath(
      $this->nodeDefinition,
      'field_integer.0.value'
    );

    $this->assertSame($target_definition, $fetched_definition);

    $fetched_definition = $this->dataFetcher->fetchDefinitionByPropertyPath(
      $this->nodeDefinition,
      'field_integer.1.value'
    );

    $this->assertSame($target_definition, $fetched_definition);
  }

  /**
   * @covers ::fetchDefinitionByPropertyPath
   */
  public function testFetchingInvalidProperty() {
    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage("Unable to apply data selector 'field_invalid.0.value' at 'field_invalid'");
    // This should trigger an exception.
    $this->dataFetcher->fetchDefinitionByPropertyPath(
      $this->nodeDefinition,
      'field_invalid.0.value'
    );
  }

  /**
   * @covers ::fetchDefinitionByPropertyPath
   */
  public function testFetchingField() {
    $target_definition = $this->nodeDefinition
      ->getPropertyDefinition('field_integer');

    $fetched_definition = $this->dataFetcher->fetchDefinitionByPropertyPath(
      $this->nodeDefinition,
      'field_integer'
    );

    $this->assertSame($target_definition, $fetched_definition);
  }

  /**
   * @covers ::fetchDefinitionByPropertyPath
   */
  public function testFetchingReferenceField() {
    $target_definition = $this->nodeDefinition
      ->getPropertyDefinition('uid');

    $fetched_definition = $this->dataFetcher->fetchDefinitionByPropertyPath(
      $this->nodeDefinition,
      'uid'
    );

    $this->assertSame($target_definition, $fetched_definition);
  }

  /**
   * @covers ::fetchDefinitionByPropertyPath
   */
  public function testFetchingNonComplexType() {
    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage("The data selector 'field_integer.0.value.not_existing' cannot be applied because the parent property 'value' is not a list or a complex structure");
    // This should trigger an exception.
    $this->dataFetcher->fetchDefinitionByPropertyPath(
      $this->nodeDefinition,
      'field_integer.0.value.not_existing'
    );
  }

  /**
   * @covers ::fetchDefinitionByPropertyPath
   */
  public function testFetchingFromPrimitive() {
    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage("The data selector 'unknown_property' cannot be applied because the definition of type 'string' is not a list or a complex structure");
    $definition = $this->nodeDefinition
      ->getPropertyDefinition('title')
      ->getItemDefinition()
      ->getPropertyDefinition('value');

    // This should trigger an exception.
    $this->dataFetcher->fetchDefinitionByPropertyPath(
      $definition,
      'unknown_property'
    );
  }

  /**
   * @covers ::fetchDefinitionByPropertyPath
   */
  public function testFetchingAtInvalidPosition() {
    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage("The data selector 'unknown_property' cannot be applied because the definition of type 'integer' is not a list or a complex structure");
    $list_definition = $this->typedDataManager->createListDataDefinition('integer');

    // This should trigger an exception.
    $this->dataFetcher->fetchDefinitionByPropertyPath(
      $list_definition,
      'unknown_property'
    );
  }

}
