<?php

namespace Drupal\Tests\group\Kernel;

use Drupal\Core\Session\AnonymousUserSession;
use Drupal\entity\QueryAccess\Condition;
use Drupal\entity\QueryAccess\ConditionGroup;
use Drupal\group\Entity\Access\GroupContentQueryAccessHandler;

/**
 * Tests the behavior of group content query access handler.
 *
 * @coversDefaultClass \Drupal\group\Entity\Access\GroupContentQueryAccessHandler
 * @group group
 */
class GroupContentQueryAccessHandlerTest extends GroupKernelTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = ['group_test_plugin'];

  /**
   * The query access handler.
   *
   * @var \Drupal\group\Entity\Access\GroupContentQueryAccessHandler
   */
  protected $handler;

  /**
   * The group type to run tests on.
   *
   * @var \Drupal\group\Entity\GroupTypeInterface
   */
  protected $groupType;

  /**
   * The group content type to run tests on.
   *
   * @var \Drupal\group\Entity\GroupContentTypeInterface
   */
  protected $groupContentType;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    // Create uid: 1 here so that it's skipped in test cases.
    $admin_user = $this->createUser();

    $entity_type = $this->entityTypeManager->getDefinition('group');
    $this->handler = GroupContentQueryAccessHandler::createInstance($this->container, $entity_type);

    // Create a group type where anonymous users have no access, authenticated
    // users can view group content and member users can update group content.
    $this->groupType = $this->createGroupType();

    // Enable the test plugins on the group type.
    /** @var \Drupal\group\Entity\Storage\GroupContentTypeStorageInterface $storage */
    $storage = $this->entityTypeManager->getStorage('group_content_type');
    $this->groupContentType = $storage->createFromPlugin($this->groupType, 'user_as_content');
    $this->groupContentType->save();
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
   * Tests the conditions for people with access in just the group type scope.
   *
   * @covers ::getConditions
   */
  public function testGroupTypeAccess() {
    $user = $this->createUser();

    // Allow outsiders to view any content.
    $group_role = $this->groupType->getOutsiderRole();
    $group_role->grantPermission('view user_as_content content')->save();

    // Allow members to update any content.
    $group_role = $this->groupType->getMemberRole();
    $group_role->grantPermission('update any user_as_content content')->save();

    $conditions = $this->handler->getConditions('view', $user);
    $expected_conditions = [
      new Condition('type', [$this->groupContentType->id()]),
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
   * Tests the conditions for people with access in just the group scope.
   *
   * @covers ::getConditions
   */
  public function testGroupAccess() {
    $user = $this->createUser();
    $group = $this->createGroup(['type' => $this->groupType->id()]);
    $group->addMember($user);

    // Allow members to update any content.
    $group_role = $this->groupType->getMemberRole();
    $group_role->grantPermission('update any user_as_content content')->save();

    $conditions = $this->handler->getConditions('update', $user);
    $expected_conditions = [
      new Condition('gid', [$group->id()]),
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
   * Tests the conditions for people with access in both scopes.
   *
   * @covers ::getConditions
   */
  public function testCombinedAccess() {
    $user = $this->createUser();
    $group = $this->createGroup(['type' => $this->groupType->id()]);
    $group->addMember($user);

    // Allow outsiders to view any content.
    $group_role = $this->groupType->getOutsiderRole();
    $group_role->grantPermission('view user_as_content content')->save();

    // Allow members to view any content.
    $group_role = $this->groupType->getMemberRole();
    $group_role->grantPermission('view user_as_content content')->save();

    $conditions = $this->handler->getConditions('view', $user);
    $expected_sub_condition = new ConditionGroup();
    $expected_conditions = [
      $expected_sub_condition
        ->addCondition('type', [$this->groupContentType->id()])
        ->addCondition('gid', [$group->id()], 'NOT IN'),
      new Condition('gid', [$group->id()]),
    ];
    $this->assertEquals(2, $conditions->count());
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
   * Tests the conditions for owner access in the group type scope.
   *
   * @covers ::getConditions
   */
  public function testOwnerGroupTypeAccess() {
    $user = $this->createUser();
    $group = $this->createGroup(['type' => $this->groupType->id()]);
    $group->addMember($user);

    // Allow outsiders to edit their own content.
    $group_role = $this->groupType->getOutsiderRole();
    $group_role->grantPermission('update own user_as_content content')->save();

    $conditions = $this->handler->getConditions('update', $user);
    $expected_sub_condition = new ConditionGroup();
    $expected_conditions = [
      $expected_sub_condition
        ->addCondition('uid', $user->id())
        ->addCondition('type', [$this->groupContentType->id()])
        ->addCondition('gid', [$group->id()], 'NOT IN'),
    ];

    $this->assertEquals(1, $conditions->count());
    $this->assertEquals('OR', $conditions->getConjunction());
    $this->assertEquals($expected_conditions, $conditions->getConditions());
    $this->assertEqualsCanonicalizing(['user', 'user.group_permissions', 'user.permissions'], $conditions->getCacheContexts());
    $this->assertFalse($conditions->isAlwaysFalse());

    foreach (['view', 'delete'] as $operation) {
      $conditions = $this->handler->getConditions($operation, $user);
      $this->assertEquals(0, $conditions->count());
      $this->assertEqualsCanonicalizing(['user.group_permissions', 'user.permissions'], $conditions->getCacheContexts());
      $this->assertTrue($conditions->isAlwaysFalse());
    }
  }

  /**
   * Tests the conditions for owner access in the group scope.
   *
   * @covers ::getConditions
   */
  public function testOwnerGroupAccess() {
    $user = $this->createUser();
    $group = $this->createGroup(['type' => $this->groupType->id()]);
    $group->addMember($user);

    // Allow members to edit their own content.
    $group_role = $this->groupType->getMemberRole();
    $group_role->grantPermission('update own user_as_content content')->save();

    $conditions = $this->handler->getConditions('update', $user);
    $expected_sub_condition = new ConditionGroup();
    $expected_conditions = [
      $expected_sub_condition
        ->addCondition('uid', $user->id())
        ->addCondition('gid', [$group->id()]),
    ];

    $this->assertEquals(1, $conditions->count());
    $this->assertEquals('OR', $conditions->getConjunction());
    $this->assertEquals($expected_conditions, $conditions->getConditions());
    $this->assertEqualsCanonicalizing(['user', 'user.group_permissions', 'user.permissions'], $conditions->getCacheContexts());
    $this->assertFalse($conditions->isAlwaysFalse());

    foreach (['view', 'delete'] as $operation) {
      $conditions = $this->handler->getConditions($operation, $user);
      $this->assertEquals(0, $conditions->count());
      $this->assertEqualsCanonicalizing(['user.group_permissions', 'user.permissions'], $conditions->getCacheContexts());
      $this->assertTrue($conditions->isAlwaysFalse());
    }
  }

  /**
   * Tests the conditions for admin access.
   *
   * @covers ::getConditions
   */
  public function testAdminAccess() {
    $user = $this->createUser();
    $group = $this->createGroup(['type' => $this->groupType->id()]);
    $group->addMember($user);

    // Allow members to administer all content.
    $group_role = $this->groupType->getMemberRole();
    $group_role->grantPermission('administer user_as_content')->save();

    foreach (['view', 'update', 'delete'] as $operation) {
      $conditions = $this->handler->getConditions($operation, $user);
      $expected_conditions = [
        new Condition('gid', [$group->id()]),
      ];

      $this->assertEquals(1, $conditions->count());
      $this->assertEquals('OR', $conditions->getConjunction());
      $this->assertEquals($expected_conditions, $conditions->getConditions());
      $this->assertEqualsCanonicalizing(['user.group_permissions', 'user.permissions'], $conditions->getCacheContexts());
      $this->assertFalse($conditions->isAlwaysFalse());
    }
  }

}
