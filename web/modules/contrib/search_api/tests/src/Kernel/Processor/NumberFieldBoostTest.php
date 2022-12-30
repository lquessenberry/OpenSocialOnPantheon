<?php

namespace Drupal\Tests\search_api\Kernel\Processor;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\node\NodeInterface;
use Drupal\search_api\Query\Query;
use Drupal\search_api\Query\QueryInterface;
use Drupal\search_api\Query\ResultSetInterface;

/**
 * Tests the "Number field boost" processor.
 *
 * @group search_api
 *
 * @coversDefaultClass \Drupal\search_api\Plugin\search_api\processor\NumberFieldBoost
 */
class NumberFieldBoostTest extends ProcessorTestBase {

  /**
   * The processor used for this test.
   *
   * @var \Drupal\search_api\Plugin\search_api\processor\NumberFieldBoost
   */
  protected $processor;

  /**
   * {@inheritdoc}
   */
  public function setUp($processor = NULL): void {
    parent::setUp('number_field_boost');

    // Create a page node type, if not already present.
    if (!NodeType::load('page')) {
      $page_node_type = NodeType::create([
        'type' => 'page',
        'name' => 'Page',
      ]);
      $page_node_type->save();
    }

    // Add an integer field.
    $boost_field_storage = FieldStorageConfig::create([
      'field_name' => 'field_boost',
      'entity_type' => 'node',
      'type' => 'integer',
      'cardinality' => 3,
    ]);
    $boost_field_storage->save();

    $boost_field = FieldConfig::create([
      'field_storage' => $boost_field_storage,
      'bundle' => 'page',
      'required' => FALSE,
    ]);
    $boost_field->save();

    // Create some nodes.
    $node = Node::create([
      'status' => NodeInterface::PUBLISHED,
      'type' => 'page',
      'title' => 'node 1 title',
      'body' => 'node 1 body',
      'field_boost' => [8],
    ]);
    $node->save();

    $node = Node::create([
      'status' => NodeInterface::PUBLISHED,
      'type' => 'page',
      'title' => 'node 2 title',
      'body' => 'node 2 body',
      'field_boost' => [3, 10],
    ]);
    $node->save();

    $node = Node::create([
      'status' => NodeInterface::PUBLISHED,
      'type' => 'page',
      'title' => 'node 3 title',
      'body' => 'node 3 body',
      'field_boost' => [1],
    ]);
    $node->save();

    $datasources = $this->container->get('search_api.plugin_helper')
      ->createDatasourcePlugins($this->index, [
        'entity:node',
      ]);
    $this->index->setDatasources($datasources);

    $nid_info = [
      'datasource_id' => 'entity:node',
      'property_path' => 'nid',
      'type' => 'integer',
    ];

    $boost_info = [
      'datasource_id' => 'entity:node',
      'property_path' => 'field_boost',
      'type' => 'integer',
    ];

    $fields_helper = $this->container->get('search_api.fields_helper');

    $this->index->addField($fields_helper->createField($this->index, 'nid', $nid_info));
    $this->index->addField($fields_helper->createField($this->index, 'field_boost', $boost_info));

    $this->index->save();

    \Drupal::getContainer()->get('search_api.index_task_manager')
      ->addItemsAll($this->index);
    $index_storage = \Drupal::entityTypeManager()
      ->getStorage('search_api_index');
    $index_storage->resetCache([$this->index->id()]);
    $this->index = $index_storage->load($this->index->id());
  }

  /**
   * Tests number field boost queries.
   *
   * @throws \Drupal\search_api\SearchApiException
   */
  public function testNumberFieldBoost() {
    $this->indexItems();

    $this->assertArrayHasKey('number_field_boost', $this->index->getProcessors(), 'Number field boost processor is added.');
    $processor = $this->index->getProcessor('number_field_boost');

    $result = $this->getSearchResults();
    $this->assertEquals([
      'entity:node/3:en',
      'entity:node/2:en',
      'entity:node/1:en',
    ], array_keys($result->getResultItems()));

    $configuration = [
      'boosts' => [
        'field_boost' => [
          'boost_factor' => 1.0,
          'aggregation' => 'max',
        ],
      ],
    ];
    $processor->setConfiguration($configuration);
    $this->index->addProcessor($processor);
    $this->index->save();
    $this->indexItems();

    $result = $this->getSearchResults();
    $this->assertEquals([
      'entity:node/2:en',
      'entity:node/1:en',
      'entity:node/3:en',
    ], array_keys($result->getResultItems()));

    $configuration = [
      'boosts' => [
        'field_boost' => [
          'boost_factor' => 1.0,
          'aggregation' => 'min',
        ],
      ],
    ];
    $processor->setConfiguration($configuration);
    $this->index->addProcessor($processor);
    $this->index->save();
    $this->indexItems();

    $result = $this->getSearchResults();
    $this->assertEquals([
      'entity:node/1:en',
      'entity:node/2:en',
      'entity:node/3:en',
    ], array_keys($result->getResultItems()));

    $configuration = [
      'boosts' => [
        'field_boost' => [
          'boost_factor' => 1.0,
          'aggregation' => 'avg',
        ],
      ],
    ];
    $processor->setConfiguration($configuration);
    $this->index->addProcessor($processor);
    $this->index->save();
    $this->indexItems();

    $result = $this->getSearchResults();
    $this->assertEquals([
      'entity:node/1:en',
      'entity:node/2:en',
      'entity:node/3:en',
    ], array_keys($result->getResultItems()));

    $configuration = [
      'boosts' => [
        'field_boost' => [
          'boost_factor' => 1.0,
          'aggregation' => 'sum',
        ],
      ],
    ];
    $processor->setConfiguration($configuration);
    $this->index->addProcessor($processor);
    $this->index->save();
    $this->indexItems();

    $result = $this->getSearchResults();
    $this->assertEquals([
      'entity:node/2:en',
      'entity:node/1:en',
      'entity:node/3:en',
    ], array_keys($result->getResultItems()));

    $configuration = [
      'boosts' => [
        'field_boost' => [
          'boost_factor' => 1.0,
          'aggregation' => 'first',
        ],
      ],
    ];
    $processor->setConfiguration($configuration);
    $this->index->addProcessor($processor);
    $this->index->save();
    $this->indexItems();

    $result = $this->getSearchResults();
    $this->assertEquals([
      'entity:node/1:en',
      'entity:node/2:en',
      'entity:node/3:en',
    ], array_keys($result->getResultItems()));

  }

  /**
   * Executes a search query and returns the results.
   *
   * Results will be sorted by score and ID, descending.
   *
   * @return \Drupal\search_api\Query\ResultSetInterface
   *   The search results.
   *
   * @throws \Drupal\search_api\SearchApiException
   *   Thrown if an error occurred during the search.
   */
  public function getSearchResults(): ResultSetInterface {
    return (new Query($this->index))
      ->keys('node')
      ->sort('search_api_relevance', QueryInterface::SORT_DESC)
      ->sort('search_api_id', Query::SORT_DESC)
      ->execute();
  }

}
