<?php

namespace Drupal\Tests\group\Unit;

use Drupal\Core\Config\Entity\ConfigEntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\group\GroupRoleSynchronizer;
use Drupal\group\Entity\GroupRoleInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\user\RoleInterface;

/**
 * Tests the outsider group role synchronizer service.
 *
 * @coversDefaultClass \Drupal\group\GroupRoleSynchronizer
 * @group group
 */
class GroupRoleSynchronizerTest extends UnitTestCase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface|\Prophecy\Prophecy\ProphecyInterface
   */
  protected $entityTypeManager;

  /**
   * The group role synchronizer service.
   *
   * @var \Drupal\group\GroupRoleSynchronizer
   */
  protected $groupRoleSynchronizer;

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();
    $this->entityTypeManager = $this->prophesize(EntityTypeManagerInterface::class);
    $this->groupRoleSynchronizer = new GroupRoleSynchronizer($this->entityTypeManager->reveal());
  }

  /**
   * @covers ::getGroupRoleId
   */
  public function testGetGroupRoleId() {
    $expected = 'foo-' . substr(md5('group_role_sync.bar'), 0, 9);
    $this->assertEquals($expected, $this->groupRoleSynchronizer->getGroupRoleId('foo', 'bar'));
  }

  /**
   * @covers ::getGroupRoleIdsByGroupType
   * @depends testGetGroupRoleId
   */
  public function testGetGroupRoleIdsByGroupType() {
    $this->setUpConfigEntityStorage('user_role', ['bar', 'baz']);
    $expected = [
      $this->groupRoleSynchronizer->getGroupRoleId('foo', 'bar'),
      $this->groupRoleSynchronizer->getGroupRoleId('foo', 'baz'),
    ];
    $this->assertEquals($expected, $this->groupRoleSynchronizer->getGroupRoleIdsByGroupType('foo'));
  }

  /**
   * @covers ::getGroupRoleIdsByGroupTypes
   * @depends testGetGroupRoleId
   */
  public function testGetGroupRoleIdsByGroupTypes() {
    $this->setUpConfigEntityStorage('user_role', ['bar', 'baz']);
    $expected = [
      $this->groupRoleSynchronizer->getGroupRoleId('foo', 'bar'),
      $this->groupRoleSynchronizer->getGroupRoleId('bee', 'bar'),
      $this->groupRoleSynchronizer->getGroupRoleId('foo', 'baz'),
      $this->groupRoleSynchronizer->getGroupRoleId('bee', 'baz'),
    ];
    $this->assertEquals($expected, $this->groupRoleSynchronizer->getGroupRoleIdsByGroupTypes(['foo', 'bee']));
  }

  /**
   * @covers ::getGroupRoleIdsByUserRole
   * @depends testGetGroupRoleId
   */
  public function testGetGroupRoleIdsByUserRole() {
    $this->setUpConfigEntityStorage('group_type', ['foo', 'bar']);
    $expected = [
      $this->groupRoleSynchronizer->getGroupRoleId('foo', 'baz'),
      $this->groupRoleSynchronizer->getGroupRoleId('bar', 'baz'),
    ];
    $this->assertEquals($expected, $this->groupRoleSynchronizer->getGroupRoleIdsByUserRole('baz'));
  }

  /**
   * @covers ::getGroupRoleIdsByUserRoles
   * @depends testGetGroupRoleId
   */
  public function testGetGroupRoleIdsByUserRoles() {
    $this->setUpConfigEntityStorage('group_type', ['foo', 'bar']);
    $expected = [
      $this->groupRoleSynchronizer->getGroupRoleId('foo', 'baz'),
      $this->groupRoleSynchronizer->getGroupRoleId('foo', 'ook'),
      $this->groupRoleSynchronizer->getGroupRoleId('bar', 'baz'),
      $this->groupRoleSynchronizer->getGroupRoleId('bar', 'ook'),
    ];
    $this->assertEquals($expected, $this->groupRoleSynchronizer->getGroupRoleIdsByUserRoles(['baz', 'ook']));
  }

  /**
   * Mock and set up a config entity type's storage handler.
   *
   * @param string $entity_type_id
   *   The ID of the config entity type to mock the storage for.
   * @param string[] $entity_ids
   *   The IDs of the config entities to return from an entity query.
   * @param \Drupal\Core\Config\Entity\ConfigEntityInterface[] $entities
   *   The config entities to return from a loadMultiple() call.
   */
  protected function setUpConfigEntityStorage($entity_type_id, $entity_ids = [], $entities = []) {
    $storage = $this->prophesize(ConfigEntityStorageInterface::class);

    if (!empty($entity_ids)) {
      $query = $this->prophesize(QueryInterface::class);
      $query->execute()->willReturn($entity_ids);
      $storage->getQuery()->willReturn($query->reveal());
    }

    if (!empty($entities)) {
      $storage->loadMultiple(NULL)->willReturn($entities);
      $storage->loadMultiple(array_keys($entities))->willReturn($entities);
    }

    $this->entityTypeManager->getStorage($entity_type_id)->willReturn($storage->reveal());
  }

}
