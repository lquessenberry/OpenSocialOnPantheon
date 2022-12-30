<?php

namespace Drupal\Tests\group\Kernel;

/**
 * Tests the general access behavior of group entities.
 *
 * @coversDefaultClass \Drupal\group\Entity\Access\GroupAccessControlHandler
 * @group group
 */
class GroupAccessControlHandlerTest extends GroupKernelTestBase {

  /**
   * The group type we will use to test access on.
   *
   * @var \Drupal\group\Entity\GroupType
   */
  protected $groupType;

  /**
   * The group we will use to test access on.
   *
   * @var \Drupal\group\Entity\Group
   */
  protected $group;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    $this->installConfig(['user']);
    $this->groupType = $this->createGroupType(['id' => 'foo', 'creator_membership' => FALSE]);
    $this->group = $this->createGroup(['type' => 'foo']);
  }

  /**
   * Tests the behavior of update or delete access for groups.
   *
   * @param string $operation
   *   The operation to test.
   * @param string $permission
   *   The permission name for the operation.
   *
   * @covers ::checkAccess
   * @dataProvider updateOrDeleteAccessProvider
   */
  public function testUpdateOrDeleteAccess($operation, $permission) {
    $access_control_handler = $this->entityTypeManager->getAccessControlHandler('group');

    $this->assertFalse($this->group->access($operation), 'An outsider without the right permission has no access');
    $access_control_handler->resetCache();

    $this->group->addMember($this->getCurrentUser());
    $this->assertFalse($this->group->access($operation), 'A member without the right permission has no access');
    $access_control_handler->resetCache();

    $this->groupType->getMemberRole()->grantPermission($permission)->save();
    $this->assertTrue($this->group->access($operation), 'A member with the right permission has access');
    $access_control_handler->resetCache();

    $this->group->set('status', FALSE)->save();
    $this->assertTrue($this->group->access($operation), 'Unpublishing the group does not change access');
    $access_control_handler->resetCache();

    $this->group->removeMember($this->getCurrentUser());
    $this->assertFalse($this->group->access($operation), 'Leaving the group does change access');
  }

  /**
   * Data provider for testUpdateOrDeleteAccess().
   *
   * @return array
   *   A list of testUpdateOrDeleteAccess method arguments.
   */
  public function updateOrDeleteAccessProvider() {
    return [
      'update-access' => [
        'update',
        'edit group',
      ],
      'delete-access' => [
        'delete',
        'delete group',
      ],
    ];
  }

  /**
   * Tests the behavior of view access for groups.
   *
   * @covers ::checkAccess
   */
  public function testViewAccess() {
    $access_control_handler = $this->entityTypeManager->getAccessControlHandler('group');

    $this->assertFalse($this->group->access('view'), 'An outsider without the right permission has no access');
    $access_control_handler->resetCache();

    $this->group->addMember($this->getCurrentUser());
    $this->assertFalse($this->group->access('view'), 'A member without the right permission has no access');
    $access_control_handler->resetCache();

    $this->groupType->getMemberRole()->grantPermission('view group')->save();
    $this->assertTrue($this->group->access('view'), 'A member with the right permission has access');
    $access_control_handler->resetCache();

    $this->group->set('status', FALSE)->save();
    $this->assertFalse($this->group->access('view'), 'Unpublishing the group denies access');
    $access_control_handler->resetCache();

    $this->groupType->getMemberRole()->grantPermission('view own unpublished group')->save();
    $this->assertTrue($this->group->access('view'), 'A member and owner with the view own unpublished permission has access');
    $access_control_handler->resetCache();

    $this->group->set('uid', 1)->save();
    $this->assertFalse($this->group->access('view'), 'Changing the group owner once again denies access');
    $access_control_handler->resetCache();

    $this->groupType->getMemberRole()->grantPermission('view any unpublished group')->save();
    $this->assertTrue($this->group->access('view'), 'A member with the view any unpublished permission has access');
    $access_control_handler->resetCache();

    $this->group->removeMember($this->getCurrentUser());
    $this->assertFalse($this->group->access('view'), 'Leaving the group once again revokes access');
  }

  /**
   * Tests the behavior of create access for groups.
   *
   * @covers ::checkCreateAccess
   */
  public function testCreateAccess() {
    $access_control_handler = $this->entityTypeManager->getAccessControlHandler('group');

    $this->assertFalse($access_control_handler->createAccess('foo'), 'A user without the right permission has no access');
    $access_control_handler->resetCache();

    $this->entityTypeManager->getStorage('user_role')
      ->load('authenticated')
      ->grantPermission('create foo group')
      ->save();
    $this->assertTrue($access_control_handler->createAccess('foo'), 'A user with the right permission has access');
  }

}
