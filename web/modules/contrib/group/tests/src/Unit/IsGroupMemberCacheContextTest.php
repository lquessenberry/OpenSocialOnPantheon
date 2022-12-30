<?php

namespace Drupal\Tests\group\Unit;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\ContentEntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\group\Cache\Context\IsGroupMemberCacheContext;
use Drupal\group\Entity\GroupInterface;
use Drupal\group\GroupMembership;
use Drupal\group\GroupMembershipLoaderInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\user\UserInterface;
use Prophecy\Argument;

/**
 * Tests the user.is_group_member:%group_id cache context.
 *
 * @coversDefaultClass \Drupal\group\Cache\Context\IsGroupMemberCacheContext
 * @group group
 */
class IsGroupMemberCacheContextTest extends UnitTestCase {

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * A dummy group to use in other prophecies.
   *
   * @var \Drupal\group\Entity\GroupInterface
   */
  protected $group;

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();
    $this->currentUser = $this->prophesize(AccountProxyInterface::class)->reveal();
    $this->group = $this->prophesize(GroupInterface::class)->reveal();
  }

  /**
   * Tests getting the context value from a non-calculated cache context.
   *
   * @covers ::getContext
   */
  public function testGetContextWithoutId() {
    $cache_context = new IsGroupMemberCacheContext(
      $this->currentUser,
      $this->createEntityTypeManager(1)->reveal(),
      $this->createGroupMembershipLoader(FALSE)->reveal()
    );

    $this->expectException(\LogicException::class);
    $this->expectExceptionMessage('No group ID provided for user.is_group_member cache context.');
    $cache_context->getContext();
  }

  /**
   * Tests getting the context value while specifying a non-existent group.
   *
   * @covers ::getContext
   */
  public function testGetContextWithInvalidGroupId() {
    $cache_context = new IsGroupMemberCacheContext(
      $this->currentUser,
      $this->createEntityTypeManager(1)->reveal(),
      $this->createGroupMembershipLoader(FALSE)->reveal()
    );

    $this->expectException(\LogicException::class);
    $this->expectExceptionMessage('Incorrect group ID provided for user.is_group_member cache context.');
    $cache_context->getContext(2);
  }

  /**
   * Tests getting the context value when the user is a member.
   *
   * @covers ::getContext
   */
  public function testGetContextMember() {
    $cache_context = new IsGroupMemberCacheContext(
      $this->currentUser,
      $this->createEntityTypeManager(1)->reveal(),
      $this->createGroupMembershipLoader(TRUE)->reveal()
    );
    $this->assertSame('1', $cache_context->getContext(1));
  }

  /**
   * Tests getting the context value when the user is not a member.
   *
   * @covers ::getContext
   */
  public function testGetContextNotMember() {
    $cache_context = new IsGroupMemberCacheContext(
      $this->currentUser,
      $this->createEntityTypeManager(1)->reveal(),
      $this->createGroupMembershipLoader(FALSE)->reveal()
    );
    $this->assertSame('0', $cache_context->getContext(1));
  }

  /**
   * Tests getting the cacheable metadata from a non-calculated cache context.
   *
   * @covers ::getCacheableMetadata
   */
  public function testGetCacheableMetadataWithoutId() {
    $cache_context = new IsGroupMemberCacheContext(
      $this->currentUser,
      $this->createEntityTypeManager(1)->reveal(),
      $this->createGroupMembershipLoader(FALSE)->reveal()
    );

    $this->expectException(\LogicException::class);
    $this->expectExceptionMessage('No group ID provided for user.is_group_member cache context.');
    $cache_context->getCacheableMetadata();
  }

  /**
   * Tests getting the cacheable metadata for a valid cache context.
   *
   * @covers ::getCacheableMetadata
   */
  public function testGetCacheableMetadata() {
    $user = $this->prophesize(UserInterface::class);
    $user->getCacheContexts()->willReturn([]);
    $user->getCacheTags()->willReturn(['user:1']);
    $user->getCacheMaxAge()->willReturn(-1);
    $user = $user->reveal();

    $current_user = $this->prophesize(AccountProxyInterface::class);
    $current_user->getAccount()->willReturn($user);

    $cache_context = new IsGroupMemberCacheContext(
      $current_user->reveal(),
      $this->createEntityTypeManager(1)->reveal(),
      $this->createGroupMembershipLoader(TRUE)->reveal()
    );

    $this->assertEquals(CacheableMetadata::createFromObject($user), $cache_context->getCacheableMetadata(1));
  }

  /**
   * Creates an EntityTypeManagerInterface prophecy.
   *
   * @param int $group_id
   *   The group ID that the group storage will be able to load.
   *
   * @return \Prophecy\Prophecy\ObjectProphecy
   *   The prophesized entity type manager.
   */
  protected function createEntityTypeManager($group_id) {
    $prophecy = $this->prophesize(EntityTypeManagerInterface::class);

    $storage = $this->prophesize(ContentEntityStorageInterface::class);
    $storage->load(Argument::any())->willReturn(NULL);
    $storage->load($group_id)->willReturn($this->group);
    $prophecy->getStorage('group')->willReturn($storage->reveal());

    return $prophecy;
  }

  /**
   * Creates a GroupMembershipLoaderInterface prophecy.
   *
   * @param bool $is_member
   *   Whether this will find the member or not.
   *
   * @return \Prophecy\Prophecy\ObjectProphecy
   *   The prophesized group membership loader.
   */
  protected function createGroupMembershipLoader($is_member) {
    $prophecy = $this->prophesize(GroupMembershipLoaderInterface::class);
    $return = $is_member ? $this->prophesize(GroupMembership::class)->reveal() : $is_member;
    $prophecy->load($this->group, $this->currentUser)->willReturn($return);
    return $prophecy;
  }

}
