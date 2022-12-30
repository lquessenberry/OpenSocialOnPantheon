<?php

namespace Drupal\Tests\group\Unit;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\ContentEntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\group\Cache\Context\RouteGroupCacheContext;
use Drupal\group\Entity\GroupInterface;
use Drupal\group\Entity\GroupTypeInterface;
use Drupal\Tests\UnitTestCase;

/**
 * Tests the route.group cache context.
 *
 * @coversDefaultClass \Drupal\group\Cache\Context\RouteGroupCacheContext
 * @group group
 */
class RouteGroupCacheContextTest extends UnitTestCase {

  /**
   * The current route match object.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface|\Prophecy\Prophecy\ProphecyInterface
   */
  protected $currentRouteMatch;

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface|\Prophecy\Prophecy\ProphecyInterface
   */
  protected $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();
    $this->currentRouteMatch = $this->prophesize(RouteMatchInterface::class);
    $this->entityTypeManager = $this->prophesize(EntityTypeManagerInterface::class);
  }

  /**
   * Tests getting the context value when there is no group on the route.
   *
   * @covers ::getContext
   */
  public function testGetContextNoGroup() {
    $this->currentRouteMatch->getParameter('group')->willReturn(NULL);
    $this->currentRouteMatch->getRouteName()->willReturn('foo');

    $cache_context = new RouteGroupCacheContext($this->currentRouteMatch->reveal(), $this->entityTypeManager->reveal());
    $this->assertSame('group.none', $cache_context->getContext());
  }

  /**
   * Tests getting the context value when there is a group on the route.
   *
   * @covers ::getContext
   */
  public function testGetContextWithGroup() {
    $group = $this->prophesize(GroupInterface::class);
    $group->id()->willReturn(1);

    $this->currentRouteMatch->getParameter('group')->willReturn($group->reveal());

    $cache_context = new RouteGroupCacheContext($this->currentRouteMatch->reveal(), $this->entityTypeManager->reveal());
    $this->assertSame(1, $cache_context->getContext());
  }

  /**
   * Tests getting the context value when on the group add form route.
   *
   * @covers ::getContext
   */
  public function testGetContextOnGroupAddForm() {
    $group = $this->prophesize(GroupInterface::class);
    $group->id()->willReturn(NULL);
    $group->bundle()->willReturn('foo');

    $group_type = $this->prophesize(GroupTypeInterface::class);
    $group_type->id()->willReturn('foo');

    $this->currentRouteMatch->getParameter('group')->willReturn(NULL);
    $this->currentRouteMatch->getParameter('group_type')->willReturn($group_type->reveal());
    $this->currentRouteMatch->getRouteName()->willReturn('entity.group.add_form');

    $storage = $this->prophesize(ContentEntityStorageInterface::class);
    $storage->create(['type' => 'foo'])->willReturn($group->reveal());
    $this->entityTypeManager->getStorage('group')->willReturn($storage->reveal());

    $cache_context = new RouteGroupCacheContext($this->currentRouteMatch->reveal(), $this->entityTypeManager->reveal());
    $this->assertSame('foo', $cache_context->getContext());
  }

  /**
   * Tests getting the cacheable metadata for the cache context.
   *
   * @covers ::getCacheableMetadata
   */
  public function testGetCacheableMetadata() {
    $cache_context = new RouteGroupCacheContext($this->currentRouteMatch->reveal(), $this->entityTypeManager->reveal());
    $this->assertEquals(new CacheableMetadata(), $cache_context->getCacheableMetadata());
  }

}
