<?php

namespace Drupal\Tests\graphql\Kernel\Framework;

use Drupal\Tests\graphql\Kernel\GraphQLTestBase;

/**
 * Tests the entire query result pipeline when using persisted queries.
 *
 * @group graphql
 */
class PersistedQueriesTest extends GraphQLTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'graphql_persisted_queries_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $schema = <<<GQL
      schema {
        query: Query
      }
      type Query {
        field_one: String
        field_two: String
        field_three: Link
      }
      type Link {
        url: String
        title: String
      }
GQL;

    $this->setUpSchema($schema);
    $this->mockResolver('Query', 'field_one', 'this is the field one');
    $this->mockResolver('Query', 'field_two', 'this is the field two');
    $this->mockResolver('Query', 'field_three', []);
    $this->mockResolver('Link', 'url', 'https://www.ecosia.org');
    $this->mockResolver('Link', 'title', 'Ecosia');

    /** @var \Drupal\graphql\Plugin\DataProducerPluginManager $manager */
    $manager = $this->container->get('plugin.manager.graphql.persisted_query');

    $this->plugin_one = $manager->createInstance('persisted_query_plugin_one');
    $this->plugin_two = $manager->createInstance('persisted_query_plugin_two');
    $this->plugin_three = $manager->createInstance('persisted_query_plugin_three');
  }

  /**
   * Test a simple query result.
   *
   * @dataProvider persistedQueriesDataProvider
   */
  public function testPersistedQueries(array $instanceIds, string $queryId, array $expected): void {
    // Before adding the persisted query plugins to the server, we want to make
    // sure that there are no existing plugins already there.
    $this->server->removeAllPersistedQueryInstances();
    foreach ($instanceIds as $index => $instanceId) {
      $this->{$instanceId}->setWeight($index);
      $this->server->addPersistedQueryInstance($this->{$instanceId});
    }
    $this->server->save();

    $result = $this->query($queryId, NULL, [], NULL, TRUE);

    $this->assertSame(200, $result->getStatusCode());
    $this->assertSame($expected, json_decode($result->getContent(), TRUE));
  }

  /**
   * Data provider for testPersistedQueries().
   */
  public function persistedQueriesDataProvider(): array {
    return [
      // This is the most usual case, all the three plugins are enabled.
      [
        ['plugin_one', 'plugin_two', 'plugin_three'],
        'query_1',
        [
          'data' => [
            'field_one' => 'this is the field one',
          ],
        ],
      ],
      // Same as previous, but with a different order.
      [
        ['plugin_two', 'plugin_one', 'plugin_three'],
        'query_1',
        [
          'data' => [
            'field_two' => 'this is the field two',
          ],
        ],
      ],
      // Execute a query that actually exists only in the last plugin.
      [
        ['plugin_one', 'plugin_two', 'plugin_three'],
        'query_2',
        [
          'data' => [
            'field_three' => [
              'url' => 'https://www.ecosia.org',
              'title' => 'Ecosia',
            ],
          ],
        ],
      ],
    ];
  }

}
