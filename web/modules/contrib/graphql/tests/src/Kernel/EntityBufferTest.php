<?php

namespace Drupal\Tests\graphql\Kernel;

use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;

/**
 * Tests the entity buffer system that it returns the correct cache metadata.
 *
 * @group graphql
 */
class EntityBufferTest extends GraphQLTestBase {

  /**
   * @var string[]
   */
  protected $nodeIds = [];

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

    foreach (range(1, 3) as $i) {
      $node = Node::create([
        'title' => 'Node ' . $i,
        'type' => 'test',
      ]);
      $node->save();
      $this->nodeIds[] = $node->id();
    }

    $schema = <<<GQL
      type Query {
        node(id: String): Node
      }

      type Node {
        title: String!
      }
GQL;

    $this->setUpSchema($schema);
  }

  /**
   * Tests the entity buffer.
   */
  public function testEntityBuffer(): void {
    $query = <<<GQL
      query {
        a:node(id: "1") {
          title
        }

        b:node(id: "2") {
          title
        }

        c:node(id: "3") {
          title
        }
      }
GQL;

    $this->mockResolver('Query', 'node',
      $this->builder->produce('entity_load')
        ->map('type', $this->builder->fromValue('node'))
        ->map('id', $this->builder->fromArgument('id'))
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
