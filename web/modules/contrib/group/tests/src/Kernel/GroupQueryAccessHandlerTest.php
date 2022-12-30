<?php

namespace Drupal\Tests\group\Kernel;

use Drupal\Core\Session\AnonymousUserSession;
use Drupal\entity\QueryAccess\Condition;
use Drupal\entity\QueryAccess\ConditionGroup;
use Drupal\group\Entity\Access\GroupQueryAccessHandler;

/**
 * Tests the behavior of group query access handler.
 *
 * @coversDefaultClass \Drupal\group\Entity\Access\GroupQueryAccessHandler
 * @group group
 */
class GroupQueryAccessHandlerTest extends GroupKernelTestBase {

  /**
   * The query access handler.
   *
   * @var \Drupal\group\Entity\Access\GroupQueryAccessHandler
   */
  protected $handler;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    $entity_type = $this->entityTypeManager->getDefinition('group');
    $this->handler = GroupQueryAccessHandler::createInstance($this->container, $entity_type);
  }

  /**
   * Tests that the the query is not altered for people who can bypass access.
   *
   * @covers ::getConditions
   */
  public function testBypassAccess() {
    $user = $this->createUser([], ['bypass group access']);
    foreach (['view', 'update', 'delete'] as $operation) {
      $conditions = $this->handler->getConditions($operation, $user);
      $this->assertEquals(0, $conditions->count());
      $this->assertEqualsCanonicalizing(['user.permissions'], $conditions->getCacheContexts());
      $this->assertFalse($conditions->isAlwaysFalse());
    }
  }

  /**
   * Tests that the query has no results for people without any access.
   *
   * @covers ::getConditions
   */
  public function testNoAccess() {
    $user = new AnonymousUserSession();
    foreach (['view', 'update', 'delete'] as $operation) {
      $conditions = $this->handler->getConditions($operation, $user);
      $this->assertEquals(0, $conditions->count());
      $this->assertEqualsCanonicalizing(['user.group_permissions', 'user.permissions'], $conditions->getCacheContexts());
      $this->assertTrue($conditions->isAlwaysFalse());
    }
  }

  /**
   * Tests the conditions for people with view access in just the type scope.
   *
   * @covers ::getConditions
   */
  public function testOnlyGroupTypeViewAccess() {
    $user = $this->getCurrentUser();

    $conditions = $this->handler->getConditions('view', $user);
    $expected_conditions = [
      (new ConditionGroup())
        ->addCondition('status', 1)
        ->addCondition('type', ['default']),
    ];
    $this->assertEquals(1, $conditions->count());
    $this->assertEquals($expected_conditions, $conditions->getConditions());
    $this->assertEqualsCanonicalizing(['user.group_permissions', 'user.permissions'], $conditions->getCacheContexts());
    $this->assertFalse($conditions->isAlwaysFalse());

    foreach (['update', 'delete'] as $operation) {
      $conditions = $this->handler->getConditions($operation, $user);
      $this->assertEquals(0, $conditions->count());
      $this->assertEqualsCanonicalizing(['user.group_permissions', 'user.permissions'], $conditions->getCacheContexts());
      $this->assertTrue($conditions->isAlwaysFalse());
    }
  }

  /**
   * Tests the conditions for people with update access in just the type scope.
   *
   * @covers ::getConditions
   */
  public function testOnlyGroupTypeUpdateAccess() {
    $user = $this->getCurrentUser();
    $this->entityTypeManager->getStorage('group_role')
      ->load('default-outsider')
      ->grantPermission('edit group')
      ->revokePermission('view group')
      ->save();

    $conditions = $this->handler->getConditions('update', $user);
    $expected_conditions = [
      new Condition('type', ['default']),
    ];
    $this->assertEquals(1, $conditions->count());
    $this->assertEquals($expected_conditions, $conditions->getConditions());
    $this->assertEqualsCanonicalizing(['user.group_permissions', 'user.permissions'], $conditions->getCacheContexts());
    $this->assertFalse($conditions->isAlwaysFalse());

    foreach (['view', 'delete'] as $operation) {
      $conditions = $this->handler->getConditions($operation, $user);
      $this->assertEquals(0, $conditions->count());
      $this->assertEqualsCanonicalizing(['user.group_permissions', 'user.permissions'], $conditions->getCacheContexts());
      $this->assertTrue($conditions->isAlwaysFalse());
    }
  }

  /**
   * Tests the conditions for people with update access in just the type scope.
   *
   * @covers ::getConditions
   */
  public function testOnlyGroupTypeDeleteAccess() {
    $user = $this->getCurrentUser();
    $this->entityTypeManager->getStorage('group_role')
      ->load('default-outsider')
      ->grantPermission('delete group')
      ->revokePermission('view group')
      ->save();

    $conditions = $this->handler->getConditions('delete', $user);
    $expected_conditions = [
      new Condition('type', ['default']),
    ];
    $this->assertEquals(1, $conditions->count());
    $this->assertEquals($expected_conditions, $conditions->getConditions());
    $this->assertEqualsCanonicalizing(['user.group_permissions', 'user.permissions'], $conditions->getCacheContexts());
    $this->assertFalse($conditions->isAlwaysFalse());

    foreach (['view', 'update'] as $operation) {
      $conditions = $this->handler->getConditions($operation, $user);
      $this->assertEquals(0, $conditions->count());
      $this->assertEqualsCanonicalizing(['user.group_permissions', 'user.permissions'], $conditions->getCacheContexts());
      $this->assertTrue($conditions->isAlwaysFalse());
    }
  }

  /**
   * Tests the conditions for people with view access in just the group scope.
   *
   * @covers ::getConditions
   */
  public function testOnlyGroupViewAccess() {
    $user = $this->getCurrentUser();
    $group = $this->createGroup();
    $group->addMember($user);

    // Remove the 'view group' permission from the default group type's outsider
    // role so the user only has the permission for the groups they are in.
    $this->entityTypeManager->getStorage('group_role')
      ->load('default-outsider')
      ->revokePermission('view group')
      ->save();

    $conditions = $this->handler->getConditions('view', $user);
    $expected_conditions = [
      (new ConditionGroup())
        ->addCondition('status', 1)
        ->addCondition('id', [$group->id()]),
    ];
    $this->assertEquals(1, $conditions->count());
    $this->assertEquals($expected_conditions, $conditions->getConditions());
    $this->assertEqualsCanonicalizing(['user.group_permissions', 'user.permissions'], $conditions->getCacheContexts());
    $this->assertFalse($conditions->isAlwaysFalse());

    foreach (['update', 'delete'] as $operation) {
      $conditions = $this->handler->getConditions($operation, $user);
      $this->assertEquals(0, $conditions->count());
      $this->assertEqualsCanonicalizing(['user.group_permissions', 'user.permissions'], $conditions->getCacheContexts());
      $this->assertTrue($conditions->isAlwaysFalse());
    }
  }

  /**
   * Tests the conditions for people with update access in just the group scope.
   *
   * @covers ::getConditions
   */
  public function testOnlyGroupUpdateAccess() {
    $user = $this->getCurrentUser();
    $group = $this->createGroup();
    $group->addMember($user);

    // Remove the 'view group' permission from the default group type's outsider
    // role so the user only has the permission for the groups they are in.
    $this->entityTypeManager->getStorage('group_role')
      ->load('default-outsider')
      ->revokePermission('view group')
      ->save();

    // Make sure members have access.
    $this->entityTypeManager->getStorage('group_role')
      ->load('default-member')
      ->grantPermission('edit group')
      ->revokePermission('view group')
      ->save();

    $conditions = $this->handler->getConditions('update', $user);
    $expected_conditions = [
      new Condition('id', [$group->id()]),
    ];
    $this->assertEquals(1, $conditions->count());
    $this->assertEquals($expected_conditions, $conditions->getConditions());
    $this->assertEqualsCanonicalizing(['user.group_permissions', 'user.permissions'], $conditions->getCacheContexts());
    $this->assertFalse($conditions->isAlwaysFalse());

    foreach (['view', 'delete'] as $operation) {
      $conditions = $this->handler->getConditions($operation, $user);
      $this->assertEquals(0, $conditions->count());
      $this->assertEqualsCanonicalizing(['user.group_permissions', 'user.permissions'], $conditions->getCacheContexts());
      $this->assertTrue($conditions->isAlwaysFalse());
    }
  }

  /**
   * Tests the conditions for people with delete access in just the group scope.
   *
   * @covers ::getConditions
   */
  public function testOnlyGroupDeleteAccess() {
    $user = $this->getCurrentUser();
    $group = $this->createGroup();
    $group->addMember($user);

    // Remove the 'view group' permission from the default group type's outsider
    // role so the user only has the permission for the groups they are in.
    $this->entityTypeManager->getStorage('group_role')
      ->load('default-outsider')
      ->revokePermission('view group')
      ->save();

    // Make sure members have access.
    $this->entityTypeManager->getStorage('group_role')
      ->load('default-member')
      ->grantPermission('delete group')
      ->revokePermission('view group')
      ->save();

    $conditions = $this->handler->getConditions('delete', $user);
    $expected_conditions = [
      new Condition('id', [$group->id()]),
    ];
    $this->assertEquals(1, $conditions->count());
    $this->assertEquals($expected_conditions, $conditions->getConditions());
    $this->assertEqualsCanonicalizing(['user.group_permissions', 'user.permissions'], $conditions->getCacheContexts());
    $this->assertFalse($conditions->isAlwaysFalse());

    foreach (['view', 'update'] as $operation) {
      $conditions = $this->handler->getConditions($operation, $user);
      $this->assertEquals(0, $conditions->count());
      $this->assertEqualsCanonicalizing(['user.group_permissions', 'user.permissions'], $conditions->getCacheContexts());
      $this->assertTrue($conditions->isAlwaysFalse());
    }
  }

  /**
   * Tests the conditions for people with view access in both scopes.
   *
   * @covers ::getConditions
   */
  public function testCombinedViewAccess() {
    $user = $this->getCurrentUser();
    $group = $this->createGroup();
    $group->addMember($user);

    $conditions = $this->handler->getConditions('view', $user);
    $expected_conditions = [
      (new ConditionGroup('AND'))
        ->addCondition('status', 1)
        ->addCondition(
          (new ConditionGroup('OR'))
            ->addCondition(
              (new ConditionGroup())
                ->addCondition('type', ['default'])
                ->addCondition('id', [$group->id()], 'NOT IN')
            )
            ->addCondition('id', [$group->id()])
        ),
    ];
    $this->assertEquals(1, $conditions->count());
    $this->assertEquals('OR', $conditions->getConjunction());
    $this->assertEquals($expected_conditions, $conditions->getConditions());
    $this->assertEqualsCanonicalizing(['user.group_permissions', 'user.permissions'], $conditions->getCacheContexts());
    $this->assertFalse($conditions->isAlwaysFalse());

    foreach (['update', 'delete'] as $operation) {
      $conditions = $this->handler->getConditions($operation, $user);
      $this->assertEquals(0, $conditions->count());
      $this->assertEqualsCanonicalizing(['user.group_permissions', 'user.permissions'], $conditions->getCacheContexts());
      $this->assertTrue($conditions->isAlwaysFalse());
    }
  }

  /**
   * Tests the conditions for people with update access in both scopes.
   *
   * @covers ::getConditions
   */
  public function testCombinedUpdateAccess() {
    $user = $this->getCurrentUser();
    $group = $this->createGroup();
    $group->addMember($user);

    $this->entityTypeManager->getStorage('group_role')
      ->load('default-member')
      ->grantPermission('edit group')
      ->revokePermission('view group')
      ->save();

    $this->entityTypeManager->getStorage('group_role')
      ->load('default-outsider')
      ->grantPermission('edit group')
      ->revokePermission('view group')
      ->save();

    $conditions = $this->handler->getConditions('update', $user);
    $expected_conditions = [
      (new ConditionGroup())
        ->addCondition('type', ['default'])
        ->addCondition('id', [$group->id()], 'NOT IN'),
      new Condition('id', [$group->id()]),
    ];
    $this->assertEquals(2, $conditions->count());
    $this->assertEquals('OR', $conditions->getConjunction());
    $this->assertEquals($expected_conditions, $conditions->getConditions());
    $this->assertEqualsCanonicalizing(['user.group_permissions', 'user.permissions'], $conditions->getCacheContexts());
    $this->assertFalse($conditions->isAlwaysFalse());

    foreach (['view', 'delete'] as $operation) {
      $conditions = $this->handler->getConditions($operation, $user);
      $this->assertEquals(0, $conditions->count());
      $this->assertEqualsCanonicalizing(['user.group_permissions', 'user.permissions'], $conditions->getCacheContexts());
      $this->assertTrue($conditions->isAlwaysFalse());
    }
  }

  /**
   * Tests the conditions for people with delete access in both scopes.
   *
   * @covers ::getConditions
   */
  public function testCombinedDeleteAccess() {
    $user = $this->getCurrentUser();
    $group = $this->createGroup();
    $group->addMember($user);

    $this->entityTypeManager->getStorage('group_role')
      ->load('default-member')
      ->grantPermission('delete group')
      ->revokePermission('view group')
      ->save();

    $this->entityTypeManager->getStorage('group_role')
      ->load('default-outsider')
      ->grantPermission('delete group')
      ->revokePermission('view group')
      ->save();

    $conditions = $this->handler->getConditions('delete', $user);
    $expected_conditions = [
      (new ConditionGroup())
        ->addCondition('type', ['default'])
        ->addCondition('id', [$group->id()], 'NOT IN'),
      new Condition('id', [$group->id()]),
    ];
    $this->assertEquals(2, $conditions->count());
    $this->assertEquals('OR', $conditions->getConjunction());
    $this->assertEquals($expected_conditions, $conditions->getConditions());
    $this->assertEqualsCanonicalizing(['user.group_permissions', 'user.permissions'], $conditions->getCacheContexts());
    $this->assertFalse($conditions->isAlwaysFalse());

    foreach (['view', 'update'] as $operation) {
      $conditions = $this->handler->getConditions($operation, $user);
      $this->assertEquals(0, $conditions->count());
      $this->assertEqualsCanonicalizing(['user.group_permissions', 'user.permissions'], $conditions->getCacheContexts());
      $this->assertTrue($conditions->isAlwaysFalse());
    }
  }

  /**
   * Tests the conditions for view unpublished access.
   *
   * @covers ::getConditions
   */
  public function testUnpublishedViewAccess() {
    // Repeat set-up from ::testCombinedViewAccess() as we can reuse it.
    $user = $this->getCurrentUser();
    $group = $this->createGroup();
    $group->addMember($user);

    // Create a group type that allows viewing of any unpublished groups.
    $any_unpub = $this->createGroupType(['id' => 'any_unpub']);
    $any_unpub->getOutsiderRole()->grantPermission('view any unpublished group')->save();
    $any_unpub->getMemberRole()->grantPermission('view any unpublished group')->save();
    $any_group = $this->createGroup(['type' => 'any_unpub']);
    $any_group->addMember($user);

    // Create a group type that allows viewing of own unpublished groups.
    $own_unpub = $this->createGroupType(['id' => 'own_unpub']);
    $own_unpub->getOutsiderRole()->grantPermission('view own unpublished group')->save();
    $own_unpub->getMemberRole()->grantPermission('view own unpublished group')->save();
    $own_group = $this->createGroup(['type' => 'own_unpub', 'uid' => $user->id()]);
    $own_group->addMember($user);

    $memberships = [$group->id(), $any_group->id(), $own_group->id()];
    $conditions = $this->handler->getConditions('view', $user);
    $expected_conditions = [
      (new ConditionGroup('AND'))
        ->addCondition('status', 0)
        ->addCondition(
          (new ConditionGroup('OR'))
            ->addCondition(
              (new ConditionGroup())
                ->addCondition('type', ['any_unpub'])
                ->addCondition('id', $memberships, 'NOT IN')
            )
            ->addCondition('id', [$any_group->id()])
            ->addCondition(
              (new ConditionGroup())
                ->addCondition('uid', $user->id())
                ->addCondition(
                  (new ConditionGroup('OR'))
                    ->addCondition(
                      (new ConditionGroup())
                        ->addCondition('type', ['own_unpub'])
                        ->addCondition('id', $memberships, 'NOT IN')
                    )
                    ->addCondition('id', [$own_group->id()])
                )
            )
        ),
      (new ConditionGroup('AND'))
        ->addCondition('status', 1)
        ->addCondition(
          (new ConditionGroup('OR'))
            ->addCondition(
              (new ConditionGroup())
                ->addCondition('type', ['default'])
                ->addCondition('id', $memberships, 'NOT IN')
            )
            ->addCondition('id', [$group->id()])
        ),
    ];
    $this->assertEquals(2, $conditions->count());
    $this->assertEquals('OR', $conditions->getConjunction());
    $this->assertEquals($expected_conditions, $conditions->getConditions());
    $this->assertEqualsCanonicalizing(['user', 'user.group_permissions', 'user.permissions'], $conditions->getCacheContexts());
    $this->assertFalse($conditions->isAlwaysFalse());

    // Verify that having the admin permission simplifies things.
    $any_unpub->getOutsiderRole()->grantPermission('administer group')->save();
    $own_unpub->getOutsiderRole()->grantPermission('administer group')->save();
    $any_unpub->getMemberRole()->grantPermission('administer group')->save();
    $own_unpub->getMemberRole()->grantPermission('administer group')->save();

    $conditions = $this->handler->getConditions('view', $user);
    $expected_conditions = [
      // Notice how we no longer need to check for status or owner when it comes
      // to groups that had the "view any" or "view own" permission, but now
      // have the admin permission instead.
      (new ConditionGroup())
        ->addCondition('type', ['any_unpub', 'own_unpub'])
        ->addCondition('id', $memberships, 'NOT IN'),
      new Condition('id', [$any_group->id(), $own_group->id()]),
      // Notice how this is the exact same expectation we had in the above test.
      (new ConditionGroup('AND'))
        ->addCondition('status', 1)
        ->addCondition(
          (new ConditionGroup('OR'))
            ->addCondition(
              (new ConditionGroup())
                ->addCondition('type', ['default'])
                ->addCondition('id', $memberships, 'NOT IN')
            )
            ->addCondition('id', [$group->id()])
        ),
    ];
    $this->assertEquals(3, $conditions->count());
    $this->assertEquals('OR', $conditions->getConjunction());
    $this->assertEquals($expected_conditions, $conditions->getConditions());
    // Notice how the user cache context is missing now.
    $this->assertEqualsCanonicalizing(['user.group_permissions', 'user.permissions'], $conditions->getCacheContexts());
    $this->assertFalse($conditions->isAlwaysFalse());
  }

}
