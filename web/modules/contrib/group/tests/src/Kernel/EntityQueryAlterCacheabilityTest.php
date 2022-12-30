<?php

namespace Drupal\Tests\group\Kernel;

use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\Render\RenderContext;

/**
 * Tests grouped entities query access cacheability.
 *
 * @coversDefaultClass \Drupal\group\QueryAccess\EntityQueryAlter
 * @group group
 */
class EntityQueryAlterCacheabilityTest extends GroupKernelTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = ['node'];

  /**
   * The grouped entity storage to use in testing.
   *
   * @var \Drupal\Core\Entity\ContentEntityStorageInterface
   */
  protected $storage;

  /**
   * The group type to use in testing.
   *
   * @var \Drupal\group\Entity\GroupTypeInterface
   */
  protected $groupType;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->installSchema('node', ['node_access']);
    $this->installEntitySchema('node');
    $this->createNodeType(['type' => 'page']);

    $this->storage = $this->entityTypeManager->getStorage('node');
    $this->groupType = $this->createGroupType(['creator_membership' => FALSE]);
  }

  /**
   * Tests that cacheable metadata is only bubbled when there is any.
   */
  public function testCacheableMetadataLeaks() {
    $renderer = $this->container->get('renderer');
    $storage = $this->storage;

    // Create an ungrouped node. This should not trigger the query access and
    // therefore not leak cacheable metadata.
    $this->createNode(['type' => 'page']);

    $render_context = new RenderContext();
    $renderer->executeInRenderContext($render_context, static function () use ($storage) {
      $storage->getQuery()->execute();
    });
    $this->assertTrue($render_context->isEmpty(), 'Empty cacheability was not bubbled.');

    // Install the test module so we have an access plugin for nodes.
    $this->enableModules(['group_test_plugin']);
    $this->installConfig('group_test_plugin');

    // Refresh the managers so they use the new namespaces.
    $this->entityTypeManager = $this->container->get('entity_type.manager');
    $this->pluginManager = $this->container->get('plugin.manager.group_content_enabler');

    // Install the plugin and add a node to a group so query access kicks in and
    // cacheable metadata is added to the query.
    /** @var \Drupal\group\Entity\Storage\GroupContentTypeStorageInterface $gct_storage */
    $gct_storage = $this->entityTypeManager->getStorage('group_content_type');
    $gct_storage->save($gct_storage->createFromPlugin($this->groupType, 'node_as_content:page'));
    $group = $this->createGroup(['type' => $this->groupType->id()]);
    $group->addContent($this->createNode(['type' => 'page']), 'node_as_content:page');

    $render_context = new RenderContext();
    $renderer->executeInRenderContext($render_context, static function () use ($storage) {
      $storage->getQuery()->execute();
    });
    $this->assertFalse($render_context->isEmpty(), 'Cacheability was bubbled');
    $this->assertCount(1, $render_context);
    $this->assertEqualsCanonicalizing(['group_content_list:plugin:node_as_content:article', 'group_content_list:plugin:node_as_content:page'], $render_context[0]->getCacheTags());
  }

  /**
   * Creates a node.
   *
   * @param array $values
   *   (optional) The values used to create the entity.
   *
   * @return \Drupal\node\Entity\Node
   *   The created node entity.
   */
  protected function createNode(array $values = []) {
    $node = $this->storage->create($values + [
      'title' => $this->randomString(),
    ]);
    $node->enforceIsNew();
    $this->storage->save($node);
    return $node;
  }

  /**
   * Creates a node type.
   *
   * @param array $values
   *   (optional) The values used to create the entity.
   *
   * @return \Drupal\node\Entity\NodeType
   *   The created node type entity.
   */
  protected function createNodeType(array $values = []) {
    $storage = $this->entityTypeManager->getStorage('node_type');
    $node_type = $storage->create($values + [
      'type' => $this->randomMachineName(),
      'label' => $this->randomString(),
    ]);
    $storage->save($node_type);
    return $node_type;
  }

}
