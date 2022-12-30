<?php

namespace Drupal\Tests\search_api\Kernel\Processor;

use Drupal\comment\CommentInterface;
use Drupal\comment\Entity\Comment;
use Drupal\comment\Entity\CommentType;
use Drupal\comment\Tests\CommentTestTrait;
use Drupal\Core\Database\Database;
use Drupal\Core\Session\AnonymousUserSession;
use Drupal\Core\TypedData\DataDefinitionInterface;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\node\NodeInterface;
use Drupal\Tests\search_api\Kernel\ResultsTrait;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;

/**
 * Tests the "Content access" processor.
 *
 * @group search_api
 *
 * @see \Drupal\search_api\Plugin\search_api\processor\ContentAccess
 */
class ContentAccessTest extends ProcessorTestBase {

  use CommentTestTrait;
  use ResultsTrait;

  /**
   * The nodes created for testing.
   *
   * @var \Drupal\node\Entity\Node[]
   */
  protected $nodes;

  /**
   * The comments created for testing.
   *
   * @var \Drupal\comment\Entity\Comment[]
   */
  protected $comments;

  /**
   * {@inheritdoc}
   */
  public function setUp($processor = NULL): void {
    parent::setUp('content_access');

    // Activate our custom grant.
    \Drupal::state()->set('search_api_test_add_node_access_grant', TRUE);

    // Create a node type for testing.
    $type = NodeType::create(['type' => 'page', 'name' => 'page']);
    $type->save();

    // Create anonymous user role.
    $role = Role::create([
      'id' => 'anonymous',
      'label' => 'anonymous',
    ]);
    $role->save();

    // Insert the anonymous user into the database, as the user table is inner
    // joined by \Drupal\comment\CommentStorage.
    User::create([
      'uid' => 0,
      'name' => '',
    ])->save();

    // Create a node with attached comment.
    $values = [
      'status' => NodeInterface::PUBLISHED,
      'type' => 'page',
      'title' => 'test title',
    ];
    $this->nodes[0] = Node::create($values);
    $this->nodes[0]->save();

    $comment_type = CommentType::create([
      'id' => 'comment',
      'target_entity_type_id' => 'node',
    ]);
    $comment_type->save();

    $this->installConfig(['comment']);
    $this->addDefaultCommentField('node', 'page');

    $comment = Comment::create([
      'status' => CommentInterface::PUBLISHED,
      'entity_type' => 'node',
      'entity_id' => $this->nodes[0]->id(),
      'field_name' => 'comment',
      'body' => 'test body',
      'comment_type' => $comment_type->id(),
    ]);
    $comment->save();

    $this->comments[] = $comment;

    $values = [
      'status' => NodeInterface::PUBLISHED,
      'type' => 'page',
      'title' => 'some title',
    ];
    $this->nodes[1] = Node::create($values);
    $this->nodes[1]->save();

    $values = [
      'status' => NodeInterface::NOT_PUBLISHED,
      'type' => 'page',
      'title' => 'other title',
    ];
    $this->nodes[2] = Node::create($values);
    $this->nodes[2]->save();

    // Also index users, to verify that they are unaffected by the processor.
    $datasources = \Drupal::getContainer()
      ->get('search_api.plugin_helper')
      ->createDatasourcePlugins($this->index, [
        'entity:comment',
        'entity:node',
        'entity:user',
      ]);
    $this->index->setDatasources($datasources);
    $this->index->save();

    \Drupal::getContainer()->get('search_api.index_task_manager')->addItemsAll($this->index);
    $index_storage = \Drupal::entityTypeManager()->getStorage('search_api_index');
    $index_storage->resetCache([$this->index->id()]);
    $this->index = $index_storage->load($this->index->id());
  }

  /**
   * Tests searching when content is accessible to all.
   */
  public function testQueryAccessAll() {
    $permissions = ['access content', 'access comments'];
    user_role_grant_permissions('anonymous', $permissions);
    $this->index->reindex();
    $this->indexItems();
    $this->assertEquals(5, $this->index->getTrackerInstance()->getIndexedItemsCount(), '5 items indexed, as expected.');

    $query = \Drupal::getContainer()
      ->get('search_api.query_helper')
      ->createQuery($this->index);
    $result = $query->execute();

    $expected = [
      'user' => [0],
      'comment' => [0],
      'node' => [0, 1],
    ];
    $this->assertResults($result, $expected);
  }

  /**
   * Tests searching when only comments are accessible.
   */
  public function testQueryAccessComments() {
    user_role_grant_permissions('anonymous', ['access comments']);
    $this->index->reindex();
    $this->indexItems();
    $this->assertEquals(5, $this->index->getTrackerInstance()->getIndexedItemsCount(), '5 items indexed, as expected.');

    $query = \Drupal::getContainer()
      ->get('search_api.query_helper')
      ->createQuery($this->index);
    $result = $query->execute();

    $this->assertResults($result, ['user' => [0], 'comment' => [0]]);
  }

  /**
   * Tests searching for own unpublished content.
   */
  public function testQueryAccessOwn() {
    // Create the user that will be passed into the query.
    $permissions = [
      'access content',
      'access comments',
      'view own unpublished content',
    ];
    $authenticated_user = $this->createUser($permissions);
    $uid = $authenticated_user->id();

    $values = [
      'status' => NodeInterface::NOT_PUBLISHED,
      'type' => 'page',
      'title' => 'foo',
      'uid' => $uid,
    ];
    $this->nodes[3] = Node::create($values);
    $this->nodes[3]->save();
    $this->indexItems();
    $this->assertEquals(7, $this->index->getTrackerInstance()->getIndexedItemsCount(), '7 items indexed, as expected.');

    $query = \Drupal::getContainer()
      ->get('search_api.query_helper')
      ->createQuery($this->index);
    $query->setOption('search_api_access_account', $authenticated_user);
    $result = $query->execute();

    $expected = ['user' => [0, $uid], 'node' => [3]];
    $this->assertResults($result, $expected);
  }

  /**
   * Tests building the query when content is accessible based on node grants.
   */
  public function testQueryAccessWithNodeGrants() {
    // Create the user that will be passed into the query.
    $permissions = [
      'access content',
    ];
    $authenticated_user = $this->createUser($permissions);

    Database::getConnection()->insert('node_access')
      ->fields([
        'nid' => $this->nodes[0]->id(),
        'langcode' => $this->nodes[0]->language()->getId(),
        'gid' => $authenticated_user->id(),
        'realm' => 'search_api_test',
        'grant_view' => 1,
      ])
      ->execute();

    $this->index->reindex();
    $this->indexItems();
    $query = \Drupal::getContainer()
      ->get('search_api.query_helper')
      ->createQuery($this->index);
    $query->setOption('search_api_access_account', $authenticated_user);
    $result = $query->execute();

    $expected = [
      'user' => [0, $authenticated_user->id()],
      'node' => [0],
    ];
    $this->assertResults($result, $expected);
  }

  /**
   * Tests comment indexing when all users have access to content.
   */
  public function testContentAccessAll() {
    // Deactivate our custom grant and re-save the grant records.
    \Drupal::state()->set('search_api_test_add_node_access_grant', FALSE);
    /** @var \Drupal\node\NodeAccessControlHandlerInterface $access_control_handler */
    $access_control_handler = \Drupal::entityTypeManager()
      ->getAccessControlHandler('node');
    $grants_storage = \Drupal::getContainer()->get('node.grant_storage');
    foreach ($this->nodes as $node) {
      $grants = $access_control_handler->acquireGrants($node);
      $grants_storage->write($node, $grants);
    }

    user_role_grant_permissions('anonymous', ['access content', 'access comments']);
    $items = [];
    foreach ($this->comments as $comment) {
      $items[] = [
        'datasource' => 'entity:comment',
        'item' => $comment->getTypedData(),
        'item_id' => $comment->id(),
        'text' => 'Comment: ' . $comment->id(),
      ];
    }
    $items = $this->generateItems($items);

    // Add the processor's field values to the items.
    foreach ($items as $item) {
      $this->processor->addFieldValues($item);
    }

    // Verify all items were indexed with the same "all" realm grant.
    $all = ['node_access_all:0'];
    foreach ($items as $item) {
      $this->assertEquals($all, $item->getField('node_grants')->getValues());
    }

    // Verify that the anonymous user has exactly that grant.
    $grants = node_access_grants('view', new AnonymousUserSession());
    $this->assertEquals(['all' => [0]], $grants);
  }

  /**
   * Tests comment indexing when hook_node_grants() takes effect.
   */
  public function testContentAccessWithNodeGrants() {
    $items = [];
    foreach ($this->comments as $comment) {
      $items[] = [
        'datasource' => 'entity:comment',
        'item' => $comment->getTypedData(),
        'item_id' => $comment->id(),
        'field_text' => 'Text: &' . $comment->id(),
      ];
    }
    $items = $this->generateItems($items);

    // Add the processor's field values to the items.
    foreach ($items as $item) {
      $this->processor->addFieldValues($item);
    }

    foreach ($items as $item) {
      $this->assertEquals(['node_access_search_api_test:0'], $item->getField('node_grants')->getValues());
    }
  }

  /**
   * Tests that acquiring node grants leads to re-indexing of that node.
   */
  public function testNodeGrantsChange() {
    $this->index->setOption('index_directly', FALSE)->save();
    $this->indexItems();
    $remaining = $this->index->getTrackerInstance()->getRemainingItems();
    $this->assertEquals([], $remaining, 'All items were indexed.');

    /** @var \Drupal\node\NodeAccessControlHandlerInterface $access_control_handler */
    $access_control_handler = \Drupal::entityTypeManager()
      ->getAccessControlHandler('node');
    $access_control_handler->acquireGrants($this->nodes[0]);

    $expected = [
      'entity:comment/' . $this->comments[0]->id() . ':en',
      'entity:node/' . $this->nodes[0]->id() . ':en',
    ];
    $remaining = $this->index->getTrackerInstance()->getRemainingItems();
    sort($remaining);
    $this->assertEquals($expected, $remaining, 'The expected items were marked as "changed" when changing node access grants.');
  }

  /**
   * Tests whether the "search_api_bypass_access" query option is respected.
   */
  public function testQueryAccessBypass() {
    $this->index->reindex();
    $this->indexItems();
    $this->assertEquals(5, $this->index->getTrackerInstance()->getIndexedItemsCount(), '5 items indexed, as expected.');

    $query = \Drupal::getContainer()
      ->get('search_api.query_helper')
      ->createQuery($this->index, ['search_api_bypass_access' => TRUE]);
    $result = $query->execute();

    $expected = [
      'user' => [0],
      'comment' => [0],
      'node' => [0, 1, 2],
    ];
    $this->assertResults($result, $expected);
  }

  /**
   * Tests whether the property is correctly added by the processor.
   */
  public function testAlterPropertyDefinitions() {
    // Check for added properties when no datasource is given.
    $properties = $this->processor->getPropertyDefinitions(NULL);
    $this->assertArrayHasKey('search_api_node_grants', $properties, 'The Properties where modified with the "search_api_node_grants".');
    $this->assertInstanceOf(DataDefinitionInterface::class, $properties['search_api_node_grants'], 'The "search_api_node_grants" key contains a valid DataDefinition instance.');
    $this->assertEquals('string', $properties['search_api_node_grants']->getDataType(), 'Correct DataType set in the DataDefinition.');

    // Verify that there are no properties if a datasource is given.
    $properties = $this->processor->getPropertyDefinitions($this->index->getDatasource('entity:node'));
    $this->assertEquals([], $properties, '"search_api_node_grants" property not added when datasource is given.');
  }

  /**
   * Creates a new user account.
   *
   * @param string[] $permissions
   *   The permissions to set for the user.
   *
   * @return \Drupal\user\UserInterface
   *   The new user object.
   */
  protected function createUser(array $permissions) {
    $role = Role::create(['id' => 'role', 'label' => 'Role test']);
    $role->save();
    user_role_grant_permissions($role->id(), $permissions);

    $values = [
      'uid' => 2,
      'name' => 'Test',
      'roles' => [$role->id()],
    ];
    $authenticated_user = User::create($values);
    $authenticated_user->enforceIsNew();
    $authenticated_user->save();

    return $authenticated_user;
  }

}
