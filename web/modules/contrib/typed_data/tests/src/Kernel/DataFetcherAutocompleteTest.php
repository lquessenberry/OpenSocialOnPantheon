<?php

namespace Drupal\Tests\typed_data\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\Core\Field\FieldStorageDefinitionInterface;

/**
 * Tests that data fetcher autocomplete function works correctly.
 *
 * @coversDefaultClass \Drupal\typed_data\DataFetcher
 *
 * @group typed_data
 */
class DataFetcherAutocompleteTest extends KernelTestBase {

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
   * The data definition of the global page node used for testing.
   *
   * @var \Drupal\Core\Entity\TypedData\EntityDataDefinitionInterface
   */
  protected $globalNodeDefinition;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['typed_data', 'system', 'node', 'field', 'user'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('node');

    // The global CurrentUserContext doesn't work properly without a
    // fully-installed user module.
    // @see https://www.drupal.org/project/rules/issues/2989417
    $this->container->get('module_handler')->loadInclude('user', 'install');
    user_install();

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

    $contexts = $this->container->get('context.repository')->getAvailableContexts();
    $this->globalNodeDefinition = $contexts['@node.node_route_context:node']->getContextDefinition()->getDataDefinition();
  }

  /**
   * Tests that basic autocompletion works.
   *
   * @covers ::autocompletePropertyPath
   */
  public function testAutocomplete() {
    $definitions = ['node' => $this->nodeDefinition];
    $results = $this->dataFetcher
      ->autocompletePropertyPath($definitions, 'n');

    $this->assertSame([
      [
        'value' => 'node',
        'label' => 'node',
      ],
      [
        'value' => 'node.',
        'label' => 'node...',
      ],
    ], $results);
  }

  /**
   * Tests that autocompletion of global context variables works.
   *
   * @covers ::autocompletePropertyPath
   */
  public function testGlobalAutocomplete() {
    $definitions = ['@node.node_route_context:node' => $this->globalNodeDefinition];
    $results = $this->dataFetcher
      ->autocompletePropertyPath($definitions, '@n');

    $this->assertSame([
      [
        'value' => '@node.node_route_context:node',
        'label' => '@node.node_route_context:node (Node from URL)',
      ],
      [
        'value' => '@node.node_route_context:node.',
        'label' => '@node.node_route_context:node... (Node from URL)',
      ],
    ], $results);
  }

  /**
   * Tests various node example data selectors.
   *
   * @covers ::autocompletePropertyPath
   */
  public function testNodeAutocomplete() {
    $definitions = ['node' => $this->nodeDefinition];

    // Tests that "node.uid.en" returns the suggestion "node.uid.entity".
    $results = $this->dataFetcher
      ->autocompletePropertyPath($definitions, 'node.uid.en');
    $this->assertSame([
      [
        'value' => 'node.uid.entity',
        'label' => 'node.uid.entity (User)',
      ],
      [
        'value' => 'node.uid.entity.',
        'label' => 'node.uid.entity... (User)',
      ],
    ], $results);

    // Tests that "node." returns all available fields on a node.
    $results = $this->dataFetcher
      ->autocompletePropertyPath($definitions, 'node.');
    $expected = array_merge([
      [
        'value' => 'node.changed',
        'label' => 'node.changed (Changed)',
      ],
      [
        'value' => 'node.changed.',
        'label' => 'node.changed... (Changed)',
      ],
      [
        'value' => 'node.created',
        'label' => 'node.created (Authored on)',
      ],
      [
        'value' => 'node.created.',
        'label' => 'node.created... (Authored on)',
      ],
      [
        'value' => 'node.default_langcode',
        'label' => 'node.default_langcode (Default translation)',
      ],
      [
        'value' => 'node.default_langcode.',
        'label' => 'node.default_langcode... (Default translation)',
      ],
      [
        'value' => 'node.field_integer',
        'label' => 'node.field_integer (field_integer)',
      ],
      [
        'value' => 'node.field_integer.',
        'label' => 'node.field_integer... (field_integer)',
      ],
      [
        'value' => 'node.langcode',
        'label' => 'node.langcode (Language)',
      ],
      [
        'value' => 'node.langcode.',
        'label' => 'node.langcode... (Language)',
      ],
      [
        'value' => 'node.nid',
        'label' => 'node.nid (ID)',
      ],
      [
        'value' => 'node.nid.',
        'label' => 'node.nid... (ID)',
      ],
      [
        'value' => 'node.promote',
        'label' => 'node.promote (Promoted to front page)',
      ],
      [
        'value' => 'node.promote.',
        'label' => 'node.promote... (Promoted to front page)',
      ],
    ],
    [
      [
        'value' => 'node.revision_default',
        'label' => 'node.revision_default (Default revision)',
      ],
      [
        'value' => 'node.revision_default.',
        'label' => 'node.revision_default... (Default revision)',
      ],
    ],
    [
      [
        'value' => 'node.revision_log',
        'label' => 'node.revision_log (Revision log message)',
      ],
      [
        'value' => 'node.revision_log.',
        'label' => 'node.revision_log... (Revision log message)',
      ],
      [
        'value' => 'node.revision_timestamp',
        'label' => 'node.revision_timestamp (Revision create time)',
      ],
      [
        'value' => 'node.revision_timestamp.',
        'label' => 'node.revision_timestamp... (Revision create time)',
      ],
      [
        'value' => 'node.revision_translation_affected',
        'label' => 'node.revision_translation_affected (Revision translation affected)',
      ],
      [
        'value' => 'node.revision_translation_affected.',
        'label' => 'node.revision_translation_affected... (Revision translation affected)',
      ],
      [
        'value' => 'node.revision_uid',
        'label' => 'node.revision_uid (Revision user)',
      ],
      [
        'value' => 'node.revision_uid.',
        'label' => 'node.revision_uid... (Revision user)',
      ],
      [
        'value' => 'node.status',
        'label' => 'node.status (Published)',
      ],
      [
        'value' => 'node.status.',
        'label' => 'node.status... (Published)',
      ],
      [
        'value' => 'node.sticky',
        'label' => 'node.sticky (Sticky at top of lists)',
      ],
      [
        'value' => 'node.sticky.',
        'label' => 'node.sticky... (Sticky at top of lists)',
      ],
      [
        'value' => 'node.title',
        'label' => 'node.title (Title)',
      ],
      [
        'value' => 'node.title.',
        'label' => 'node.title... (Title)',
      ],
      [
        'value' => 'node.type',
        'label' => 'node.type (Content type)',
      ],
      [
        'value' => 'node.type.',
        'label' => 'node.type... (Content type)',
      ],
      [
        'value' => 'node.uid',
        'label' => 'node.uid (Authored by)',
      ],
      [
        'value' => 'node.uid.',
        'label' => 'node.uid... (Authored by)',
      ],
      [
        'value' => 'node.uuid',
        'label' => 'node.uuid (UUID)',
      ],
      [
        'value' => 'node.uuid.',
        'label' => 'node.uuid... (UUID)',
      ],
      [
        'value' => 'node.vid',
        'label' => 'node.vid (Revision ID)',
      ],
      [
        'value' => 'node.vid.',
        'label' => 'node.vid... (Revision ID)',
      ],
    ]);
    // Because this is a huge array, run the assertion per entry as that is
    // easier for debugging.
    foreach ($expected as $index => $entry) {
      $this->assertSame($entry, $results[$index]);
    }

    // Tests that "node.uid.entity.na" returns "node.uid.entity.name".
    $results = $this->dataFetcher
      ->autocompletePropertyPath($definitions, 'node.uid.entity.na');
    $this->assertSame([
      [
        'value' => 'node.uid.entity.name',
        'label' => 'node.uid.entity.name (Name)',
      ],
      [
        'value' => 'node.uid.entity.name.',
        'label' => 'node.uid.entity.name... (Name)',
      ],
    ], $results);

    // A multi-valued field should show numeric indices suggestions.
    $results = $this->dataFetcher
      ->autocompletePropertyPath($definitions, 'node.field_integer.');
    $this->assertSame([
      [
        'value' => 'node.field_integer.0',
        'label' => 'node.field_integer.0',
      ],
      [
        'value' => 'node.field_integer.0.',
        'label' => 'node.field_integer.0...',
      ],
      [
        'value' => 'node.field_integer.1',
        'label' => 'node.field_integer.1',
      ],
      [
        'value' => 'node.field_integer.1.',
        'label' => 'node.field_integer.1...',
      ],
      [
        'value' => 'node.field_integer.2',
        'label' => 'node.field_integer.2',
      ],
      [
        'value' => 'node.field_integer.2.',
        'label' => 'node.field_integer.2...',
      ],
      [
        'value' => 'node.field_integer.value',
        'label' => 'node.field_integer.value (Integer value)',
      ],
    ], $results);

    // A single-valued field should not show numeric indices suggestions.
    $results = $this->dataFetcher
      ->autocompletePropertyPath($definitions, 'node.title.');
    $this->assertSame([
      [
        'value' => 'node.title.value',
        'label' => 'node.title.value (Text value)',
      ],
    ], $results);

    // A single-valued field should not show numeric indices suggestions.
    $results = $this->dataFetcher
      ->autocompletePropertyPath($definitions, 'n');
    $this->assertSame([
      [
        'value' => 'node',
        'label' => 'node',
      ],
      [
        'value' => 'node.',
        'label' => 'node...',
      ],
    ], $results);
  }

  /**
   * Tests various @node example data selectors.
   *
   * @covers ::autocompletePropertyPath
   */
  public function testGlobalNodeAutocomplete() {
    $definitions = ['@node.node_route_context:node' => $this->globalNodeDefinition];

    // Tests that "node.uid.en" returns the suggestion "node.uid.entity".
    $results = $this->dataFetcher
      ->autocompletePropertyPath($definitions, '@node.node_route_context:node.uid.en');
    $this->assertSame([
      [
        'value' => '@node.node_route_context:node.uid.entity',
        'label' => '@node.node_route_context:node.uid.entity (User)',
      ],
      [
        'value' => '@node.node_route_context:node.uid.entity.',
        'label' => '@node.node_route_context:node.uid.entity... (User)',
      ],
    ], $results);

    // Tests that "@node.node_route_context:node." returns all available fields
    // on a node.
    $results = $this->dataFetcher
      ->autocompletePropertyPath($definitions, '@node.node_route_context:node.');
    $expected = array_merge([
      [
        'value' => '@node.node_route_context:node.changed',
        'label' => '@node.node_route_context:node.changed (Changed)',
      ],
      [
        'value' => '@node.node_route_context:node.changed.',
        'label' => '@node.node_route_context:node.changed... (Changed)',
      ],
      [
        'value' => '@node.node_route_context:node.created',
        'label' => '@node.node_route_context:node.created (Authored on)',
      ],
      [
        'value' => '@node.node_route_context:node.created.',
        'label' => '@node.node_route_context:node.created... (Authored on)',
      ],
      [
        'value' => '@node.node_route_context:node.default_langcode',
        'label' => '@node.node_route_context:node.default_langcode (Default translation)',
      ],
      [
        'value' => '@node.node_route_context:node.default_langcode.',
        'label' => '@node.node_route_context:node.default_langcode... (Default translation)',
      ],
      [
        'value' => '@node.node_route_context:node.langcode',
        'label' => '@node.node_route_context:node.langcode (Language)',
      ],
      [
        'value' => '@node.node_route_context:node.langcode.',
        'label' => '@node.node_route_context:node.langcode... (Language)',
      ],
      [
        'value' => '@node.node_route_context:node.nid',
        'label' => '@node.node_route_context:node.nid (ID)',
      ],
      [
        'value' => '@node.node_route_context:node.nid.',
        'label' => '@node.node_route_context:node.nid... (ID)',
      ],
      [
        'value' => '@node.node_route_context:node.promote',
        'label' => '@node.node_route_context:node.promote (Promoted to front page)',
      ],
      [
        'value' => '@node.node_route_context:node.promote.',
        'label' => '@node.node_route_context:node.promote... (Promoted to front page)',
      ],
    ],
    [
      [
        'value' => '@node.node_route_context:node.revision_default',
        'label' => '@node.node_route_context:node.revision_default (Default revision)',
      ],
      [
        'value' => '@node.node_route_context:node.revision_default.',
        'label' => '@node.node_route_context:node.revision_default... (Default revision)',
      ],
    ],
    [
      [
        'value' => '@node.node_route_context:node.revision_log',
        'label' => '@node.node_route_context:node.revision_log (Revision log message)',
      ],
      [
        'value' => '@node.node_route_context:node.revision_log.',
        'label' => '@node.node_route_context:node.revision_log... (Revision log message)',
      ],
      [
        'value' => '@node.node_route_context:node.revision_timestamp',
        'label' => '@node.node_route_context:node.revision_timestamp (Revision create time)',
      ],
      [
        'value' => '@node.node_route_context:node.revision_timestamp.',
        'label' => '@node.node_route_context:node.revision_timestamp... (Revision create time)',
      ],
      [
        'value' => '@node.node_route_context:node.revision_translation_affected',
        'label' => '@node.node_route_context:node.revision_translation_affected (Revision translation affected)',
      ],
      [
        'value' => '@node.node_route_context:node.revision_translation_affected.',
        'label' => '@node.node_route_context:node.revision_translation_affected... (Revision translation affected)',
      ],
      [
        'value' => '@node.node_route_context:node.revision_uid',
        'label' => '@node.node_route_context:node.revision_uid (Revision user)',
      ],
      [
        'value' => '@node.node_route_context:node.revision_uid.',
        'label' => '@node.node_route_context:node.revision_uid... (Revision user)',
      ],
      [
        'value' => '@node.node_route_context:node.status',
        'label' => '@node.node_route_context:node.status (Published)',
      ],
      [
        'value' => '@node.node_route_context:node.status.',
        'label' => '@node.node_route_context:node.status... (Published)',
      ],
      [
        'value' => '@node.node_route_context:node.sticky',
        'label' => '@node.node_route_context:node.sticky (Sticky at top of lists)',
      ],
      [
        'value' => '@node.node_route_context:node.sticky.',
        'label' => '@node.node_route_context:node.sticky... (Sticky at top of lists)',
      ],
      [
        'value' => '@node.node_route_context:node.title',
        'label' => '@node.node_route_context:node.title (Title)',
      ],
      [
        'value' => '@node.node_route_context:node.title.',
        'label' => '@node.node_route_context:node.title... (Title)',
      ],
      [
        'value' => '@node.node_route_context:node.type',
        'label' => '@node.node_route_context:node.type (Content type)',
      ],
      [
        'value' => '@node.node_route_context:node.type.',
        'label' => '@node.node_route_context:node.type... (Content type)',
      ],
      [
        'value' => '@node.node_route_context:node.uid',
        'label' => '@node.node_route_context:node.uid (Authored by)',
      ],
      [
        'value' => '@node.node_route_context:node.uid.',
        'label' => '@node.node_route_context:node.uid... (Authored by)',
      ],
      [
        'value' => '@node.node_route_context:node.uuid',
        'label' => '@node.node_route_context:node.uuid (UUID)',
      ],
      [
        'value' => '@node.node_route_context:node.uuid.',
        'label' => '@node.node_route_context:node.uuid... (UUID)',
      ],
      [
        'value' => '@node.node_route_context:node.vid',
        'label' => '@node.node_route_context:node.vid (Revision ID)',
      ],
      [
        'value' => '@node.node_route_context:node.vid.',
        'label' => '@node.node_route_context:node.vid... (Revision ID)',
      ],
    ]);
    // Because this is a huge array, run the assertion per entry as that is
    // easier for debugging.
    foreach ($expected as $index => $entry) {
      $this->assertSame($entry, $results[$index]);
    }

    // Tests that "@node.node_route_context:node.uid.entity.na" returns
    // "@node.node_route_context:node.uid.entity.name".
    $results = $this->dataFetcher
      ->autocompletePropertyPath($definitions, '@node.node_route_context:node.uid.entity.na');
    $this->assertSame([
      [
        'value' => '@node.node_route_context:node.uid.entity.name',
        'label' => '@node.node_route_context:node.uid.entity.name (Name)',
      ],
      [
        'value' => '@node.node_route_context:node.uid.entity.name.',
        'label' => '@node.node_route_context:node.uid.entity.name... (Name)',
      ],
    ], $results);

    // A single-valued field should not show numeric indices suggestions.
    $results = $this->dataFetcher
      ->autocompletePropertyPath($definitions, '@node.node_route_context:node.title.');
    $this->assertSame([
      [
        'value' => '@node.node_route_context:node.title.value',
        'label' => '@node.node_route_context:node.title.value (Text value)',
      ],
    ], $results);

    // A single-valued field should not show numeric indices suggestions.
    $results = $this->dataFetcher
      ->autocompletePropertyPath($definitions, '@node.node_route_context:n');
    $this->assertSame([
      [
        'value' => '@node.node_route_context:node',
        'label' => '@node.node_route_context:node (Node from URL)',
      ],
      [
        'value' => '@node.node_route_context:node.',
        'label' => '@node.node_route_context:node... (Node from URL)',
      ],
    ], $results);
  }

  /**
   * Tests that autocomplete results for a flat list are correct.
   *
   * @covers ::autocompletePropertyPath
   */
  public function testListAutocomplete() {
    $list_definition = $this->typedDataManager->createListDataDefinition('integer');
    $definitions = ['list' => $list_definition];

    $results = $this->dataFetcher
      ->autocompletePropertyPath($definitions, 'list.');
    $this->assertSame([
      [
        'value' => 'list.0',
        'label' => 'list.0',
      ],
      [
        'value' => 'list.1',
        'label' => 'list.1',
      ],
      [
        'value' => 'list.2',
        'label' => 'list.2',
      ],
    ], $results);
  }

}
