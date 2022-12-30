<?php

namespace Drupal\Tests\graphql\Kernel;

use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;

/**
 * Tests the entity buffer with loading by UUID data producers.
 *
 * @group graphql
 */
class EntityUuidBufferTest extends GraphQLTestBase {

  /**
   * @var array
   */
  protected $nodeUuids = [];

  /**
   * @var \PHPUnit\Framework\MockObject\MockObject
   */
  protected $entityBuffer;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    NodeType::create([
      'type' => 'test',
      'name' => 'Test',
    ])->save();

    $this->nodeUuids = array_map(function ($i) {
      $node = Node::create([
        'title' => 'Node ' . $i,
        'type' => 'test',
      ]);

      $node->save();

      return $node->uuid();
    }, range(1, 3));

    $schema = <<<GQL
      type Query {
        node(uuid: String): Node
      }
      type Node {
        title: String!
      }
GQL;

    $this->setUpSchema($schema);
  }

  /**
   * Tests the entity UUID buffer.
   */
  public function testEntityUuidBuffer(): void {
    $query = <<<GQL
      query {
        a:node(uuid: "{$this->nodeUuids[0]}") {
          title
        }

        b:node(uuid: "{$this->nodeUuids[1]}") {
          title
        }

        c:node(uuid: "{$this->nodeUuids[2]}") {
          title
        }
      }
GQL;

    $this->mockResolver('Query', 'node',
      $this->builder->produce('entity_load_by_uuid')
        ->map('type', $this->builder->fromValue('node'))
        ->map('uuid', $this->builder->fromArgument('uuid'))
    );

    $this->mockResolver('Node', 'title',
      $this->builder->produce('entity_label')
        ->map('entity', $this->builder->fromParent())
    );

    $metadata = $this->defaultCacheMetaData();
    $metadata->addCacheTags(['node:1', 'node:2', 'node:3']);
    $this->assertResults($query, [], [
      'a' => ['title' => 'Node 1'],
      'b' => ['title' => 'Node 2'],
      'c' => ['title' => 'Node 3'],
    ], $metadata);
  }

}
