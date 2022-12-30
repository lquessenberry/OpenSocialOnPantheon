<?php

namespace Drupal\Tests\group\Unit;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\group\Access\GroupPermissionsHashGeneratorInterface;
use Drupal\group\Cache\Context\GroupPermissionsCacheContext;
use Drupal\Tests\UnitTestCase;

/**
 * Tests the user.group_permissions cache context.
 *
 * @coversDefaultClass \Drupal\group\Cache\Context\GroupPermissionsCacheContext
 * @group group
 */
class GroupPermissionsCacheContextTest extends UnitTestCase {

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface|\Prophecy\Prophecy\ProphecyInterface
   */
  protected $currentUser;

  /**
   * The permissions hash generator.
   *
   * @var \Drupal\group\Access\GroupPermissionsHashGeneratorInterface|\Prophecy\Prophecy\ProphecyInterface
   */
  protected $permissionsHashGenerator;

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();
    $this->currentUser = $this->prophesize(AccountProxyInterface::class);
    $this->permissionsHashGenerator = $this->prophesize(GroupPermissionsHashGeneratorInterface::class);
  }

  /**
   * Tests getting the context value for the current user.
   *
   * @covers ::getContext
   */
  public function testGetContext() {
    $this->permissionsHashGenerator->generateHash($this->currentUser->reveal())->willReturn('foo');
    $cache_context = new GroupPermissionsCacheContext(
      $this->currentUser->reveal(),
      $this->permissionsHashGenerator->reveal()
    );
    $this->assertSame('foo', $cache_context->getContext(), 'The cache context gets its value directly from the hash generator.');
  }

  /**
   * Tests getting the cacheable metadata from the hash generator.
   *
   * @covers ::getCacheableMetadata
   */
  public function testGetCacheableMetadata() {
    $cacheable_metadata = new CacheableMetadata();
    $cacheable_metadata->addCacheTags(["config:group.role.foo-bar"]);
    $this->permissionsHashGenerator->getCacheableMetadata($this->currentUser->reveal())->willReturn($cacheable_metadata);

    $cache_context = new GroupPermissionsCacheContext(
      $this->currentUser->reveal(),
      $this->permissionsHashGenerator->reveal()
    );
    $this->assertEquals($cacheable_metadata, $cache_context->getCacheableMetadata(), 'The cache context gets its cacheable metadata directly from the hash generator.');
  }

}
