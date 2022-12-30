<?php

namespace Drupal\Tests\group\Kernel;

use Drupal\views\Tests\ViewResultAssertionTrait;
use Drupal\views\Views;

/**
 * Tests that Group properly checks access for "complex" grouped entities.
 *
 * By complex entities we mean entities that can be published or unpublished and
 * have a way of determining who owns the entity. This leads to far more complex
 * query alters as we need to take ownership and publication state into account.
 *
 * @todo Test operations other than view.
 *
 * @coversDefaultClass \Drupal\group\QueryAccess\EntityQueryAlter
 * @group group
 */
class EntityQueryAlterComplexTest extends GroupKernelTestBase {

  use ViewResultAssertionTrait;

  /**
   * {@inheritdoc}
   */
  public static $modules = ['group_test_plugin', 'node', 'views'];

  /**
   * The grouped storage to use in testing.
   *
   * @var \Drupal\node\NodeStorageInterface
   */
  protected $storage;

  /**
   * The first group type to use in testing.
   *
   * @var \Drupal\group\Entity\GroupTypeInterface
   */
  protected $groupTypeA;

  /**
   * The second group type to use in testing.
   *
   * @var \Drupal\group\Entity\GroupTypeInterface
   */
  protected $groupTypeB;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->installConfig('group_test_plugin');
    $this->installSchema('node', ['node_access']);
    $this->installEntitySchema('node');

    $this->storage = $this->entityTypeManager->getStorage('node');
    $this->createNodeType(['type' => 'page']);
    $this->createNodeType(['type' => 'article']);

    $this->groupTypeA = $this->createGroupType(['id' => 'foo', 'creator_membership' => FALSE]);
    $this->groupTypeB = $this->createGroupType(['id' => 'bar', 'creator_membership' => FALSE]);

    /** @var \Drupal\group\Entity\Storage\GroupContentTypeStorageInterface $storage */
    $storage = $this->entityTypeManager->getStorage('group_content_type');
    $storage->save($storage->createFromPlugin($this->groupTypeA, 'node_as_content:page'));
    $storage->save($storage->createFromPlugin($this->groupTypeA, 'node_as_content:article'));
    $storage->save($storage->createFromPlugin($this->groupTypeB, 'node_as_content:page'));
    $storage->save($storage->createFromPlugin($this->groupTypeB, 'node_as_content:article'));
  }

  /**
   * Tests that regular access checks still work.
   */
  public function testRegularAccess() {
    $node_1 = $this->createNode(['type' => 'page', 'uid' => $this->createUser()->id()]);
    $node_2 = $this->createNode(['type' => 'page']);
    $this->assertQueryAccessResult([$node_1->id(), $node_2->id()], 'Regular node query access still works.');
  }

  /**
   * Tests that grouped nodes are properly hidden for members.
   */
  public function testMemberGroupAccessWithoutPermission() {
    $node_1 = $this->createNode(['type' => 'page']);
    $node_2 = $this->createNode(['type' => 'page']);

    $group = $this->createGroup(['type' => $this->groupTypeA->id()]);
    $group->addContent($node_1, 'node_as_content:page');
    $group->addMember($this->getCurrentUser());

    $this->assertQueryAccessResult([$node_2->id()], 'Only the ungrouped node shows up.');

    // Extra hardening: Re-confirm result when another group type does grant
    // access but does not contain the node.
    $this->groupTypeB->getMemberRole()->grantPermission('view any node_as_content:page entity')->save();
    $this->createGroup(['type' => $this->groupTypeB->id()])->addMember($this->getCurrentUser());
    $this->assertQueryAccessResult([$node_2->id()], 'Only the ungrouped node shows up.');
  }

  /**
   * Tests that grouped nodes are properly hidden for non-members.
   */
  public function testNonMemberGroupAccessWithoutPermission() {
    $node_1 = $this->createNode(['type' => 'page']);
    $node_2 = $this->createNode(['type' => 'page']);

    $group = $this->createGroup(['type' => $this->groupTypeA->id()]);
    $group->addContent($node_1, 'node_as_content:page');

    $this->assertQueryAccessResult([$node_2->id()], 'Only the ungrouped node shows up.');

    // Extra hardening: Re-confirm result when another group type does grant
    // access but does not contain the node.
    $this->groupTypeB->getOutsiderRole()->grantPermission('view any node_as_content:page entity')->save();
    $this->createGroup(['type' => $this->groupTypeB->id()]);
    $this->assertQueryAccessResult([$node_2->id()], 'Only the ungrouped node shows up.');
  }

  /**
   * Tests that grouped nodes are visible to members.
   */
  public function testMemberGroupAccessWithPermission() {
    $node_1 = $this->createNode(['type' => 'page']);
    $node_2 = $this->createNode(['type' => 'page']);

    $this->groupTypeA->getMemberRole()->grantPermission('administer node_as_content:page')->save();
    $group = $this->createGroup(['type' => $this->groupTypeA->id()]);
    $group->addContent($node_1, 'node_as_content:page');
    $group->addMember($this->getCurrentUser());

    $this->assertQueryAccessResult([$node_1->id(), $node_2->id()], 'Members can see grouped nodes');
  }

  /**
   * Tests that grouped nodes are visible to non-members.
   */
  public function testNonMemberGroupAccessWithPermission() {
    $node_1 = $this->createNode(['type' => 'page']);
    $node_2 = $this->createNode(['type' => 'page']);

    $this->groupTypeA->getOutsiderRole()->grantPermission('administer node_as_content:page')->save();
    $group = $this->createGroup(['type' => $this->groupTypeA->id()]);
    $group->addContent($node_1, 'node_as_content:page');
    $this->createGroup(['type' => $this->groupTypeA->id()]);

    $this->assertQueryAccessResult([$node_1->id(), $node_2->id()], 'Outsiders can see grouped nodes');
  }

  /**
   * Tests the viewing of any published entities for members.
   */
  public function testMemberViewAnyPublishedAccess() {
    $account = $this->createUser();
    $node_1 = $this->createNode(['type' => 'page']);
    $node_2 = $this->createNode(['type' => 'page']);
    $node_3 = $this->createNode(['type' => 'page', 'uid' => $account->id()]);

    $this->groupTypeA->getMemberRole()->grantPermission('view any node_as_content:page entity')->save();
    $this->groupTypeB->getMemberRole()->grantPermission('view any node_as_content:page entity')->save();

    $group_a = $this->createGroup(['type' => $this->groupTypeA->id()]);
    $group_a->addContent($node_1, 'node_as_content:page');
    $group_a->addMember($this->getCurrentUser());
    $group_a->addMember($account);

    $group_b = $this->createGroup(['type' => $this->groupTypeB->id()]);
    $group_b->addContent($node_3, 'node_as_content:page');
    $group_b->addMember($this->getCurrentUser());
    $group_b->addMember($account);

    $this->assertQueryAccessResult([$node_1->id(), $node_2->id(), $node_3->id()], 'Members can see any published nodes.');

    $this->setCurrentUser($account);
    $this->assertQueryAccessResult([$node_1->id(), $node_2->id(), $node_3->id()], 'Members can see any published nodes.');

    $this->setCurrentUser($this->createUser());
    $this->assertQueryAccessResult([$node_2->id()], 'Only the ungrouped published node shows up.');
  }

  /**
   * Tests the viewing of any published entities for non-members.
   */
  public function testNonMemberViewAnyPublishedAccess() {
    $account = $this->createUser();
    $node_1 = $this->createNode(['type' => 'page']);
    $node_2 = $this->createNode(['type' => 'page']);
    $node_3 = $this->createNode(['type' => 'page', 'uid' => $account->id()]);

    $this->groupTypeA->getOutsiderRole()->grantPermission('view any node_as_content:page entity')->save();
    $this->groupTypeB->getOutsiderRole()->grantPermission('view any node_as_content:page entity')->save();

    $group_a = $this->createGroup(['type' => $this->groupTypeA->id()]);
    $group_a->addContent($node_1, 'node_as_content:page');
    $group_a->addMember($account);

    $group_b = $this->createGroup(['type' => $this->groupTypeB->id()]);
    $group_b->addContent($node_3, 'node_as_content:page');
    $group_b->addMember($account);

    $this->assertQueryAccessResult([$node_1->id(), $node_2->id(), $node_3->id()], 'Non-members can see any published nodes.');

    $this->setCurrentUser($this->createUser());
    $this->assertQueryAccessResult([$node_1->id(), $node_2->id(), $node_3->id()], 'Non-members can see any published nodes.');

    $this->setCurrentUser($account);
    $this->assertQueryAccessResult([$node_2->id()], 'Only the ungrouped published node shows up.');
  }

  /**
   * Tests the viewing of own published entities for members.
   */
  public function testMemberViewOwnPublishedAccess() {
    $account = $this->createUser();
    $node_1 = $this->createNode(['type' => 'page']);
    $node_2 = $this->createNode(['type' => 'page']);
    $node_3 = $this->createNode(['type' => 'page', 'uid' => $account->id()]);

    $this->groupTypeA->getMemberRole()->grantPermission('view own node_as_content:page entity')->save();
    $this->groupTypeB->getMemberRole()->grantPermission('view own node_as_content:page entity')->save();

    $group_a = $this->createGroup(['type' => $this->groupTypeA->id()]);
    $group_a->addContent($node_1, 'node_as_content:page');
    $group_a->addMember($this->getCurrentUser());
    $group_a->addMember($account);

    $group_b = $this->createGroup(['type' => $this->groupTypeB->id()]);
    $group_b->addContent($node_3, 'node_as_content:page');
    $group_b->addMember($this->getCurrentUser());
    $group_b->addMember($account);

    $this->assertQueryAccessResult([$node_1->id(), $node_2->id()], 'Members can see their own published nodes.');

    $this->setCurrentUser($account);
    $this->assertQueryAccessResult([$node_2->id(), $node_3->id()], 'Members can see their own published nodes.');

    $this->setCurrentUser($this->createUser());
    $this->assertQueryAccessResult([$node_2->id()], 'Only the ungrouped published node shows up.');
  }

  /**
   * Tests the viewing of own published entities for non-members.
   */
  public function testNonMemberViewOwnPublishedAccess() {
    $account = $this->createUser();
    $node_1 = $this->createNode(['type' => 'page']);
    $node_2 = $this->createNode(['type' => 'page']);
    $node_3 = $this->createNode(['type' => 'page', 'uid' => $account->id()]);

    $this->groupTypeA->getOutsiderRole()->grantPermission('view own node_as_content:page entity')->save();
    $this->groupTypeB->getOutsiderRole()->grantPermission('view own node_as_content:page entity')->save();

    $group_a = $this->createGroup(['type' => $this->groupTypeA->id()]);
    $group_a->addContent($node_1, 'node_as_content:page');
    $group_a->addMember($account);

    $group_b = $this->createGroup(['type' => $this->groupTypeB->id()]);
    $group_b->addContent($node_3, 'node_as_content:page');
    $group_b->addMember($account);

    $this->assertQueryAccessResult([$node_1->id(), $node_2->id()], 'Non-members can see their own published nodes.');

    $this->setCurrentUser($this->createUser());
    $this->assertQueryAccessResult([$node_2->id()], 'Only the ungrouped published node shows up.');

    $this->setCurrentUser($account);
    $this->assertQueryAccessResult([$node_2->id()], 'Only the ungrouped published node shows up.');
  }

  /**
   * Tests the viewing of any unpublished entities for members.
   */
  public function testMemberViewAnyUnpublishedAccess() {
    $account = $this->createUser();
    $node_1 = $this->createNode(['type' => 'page', 'status' => 0]);
    $node_2 = $this->createNode(['type' => 'page', 'status' => 0]);
    $node_3 = $this->createNode(['type' => 'page', 'status' => 0, 'uid' => $account->id()]);

    $this->groupTypeA->getMemberRole()->grantPermission('view any unpublished node_as_content:page entity')->save();
    $this->groupTypeB->getMemberRole()->grantPermission('view any unpublished node_as_content:page entity')->save();

    $group_a = $this->createGroup(['type' => $this->groupTypeA->id()]);
    $group_a->addContent($node_1, 'node_as_content:page');
    $group_a->addMember($this->getCurrentUser());
    $group_a->addMember($account);

    $group_b = $this->createGroup(['type' => $this->groupTypeB->id()]);
    $group_b->addContent($node_3, 'node_as_content:page');
    $group_b->addMember($this->getCurrentUser());
    $group_b->addMember($account);

    $this->assertQueryAccessResult([$node_1->id(), $node_2->id(), $node_3->id()], 'Members can see any unpublished nodes.');

    $this->setCurrentUser($account);
    $this->assertQueryAccessResult([$node_1->id(), $node_2->id(), $node_3->id()], 'Members can see any unpublished nodes.');

    // This is actually a core issue, but for now unpublished nodes show up in
    // entity queries when there are no node grants defining modules.
    $this->setCurrentUser($this->createUser());
    $this->assertQueryAccessResult([$node_2->id()], 'Only the ungrouped unpublished node shows up.');
  }

  /**
   * Tests the viewing of any unpublished entities for non-members.
   */
  public function testNonMemberViewAnyUnpublishedAccess() {
    $account = $this->createUser();
    $node_1 = $this->createNode(['type' => 'page', 'status' => 0]);
    $node_2 = $this->createNode(['type' => 'page', 'status' => 0]);
    $node_3 = $this->createNode(['type' => 'page', 'status' => 0, 'uid' => $account->id()]);

    $this->groupTypeA->getOutsiderRole()->grantPermission('view any unpublished node_as_content:page entity')->save();
    $this->groupTypeB->getOutsiderRole()->grantPermission('view any unpublished node_as_content:page entity')->save();

    $group_a = $this->createGroup(['type' => $this->groupTypeA->id()]);
    $group_a->addContent($node_1, 'node_as_content:page');
    $group_a->addMember($account);

    $group_b = $this->createGroup(['type' => $this->groupTypeB->id()]);
    $group_b->addContent($node_3, 'node_as_content:page');
    $group_b->addMember($account);

    $this->assertQueryAccessResult([$node_1->id(), $node_2->id(), $node_3->id()], 'Non-members can see any unpublished nodes.');

    $this->setCurrentUser($this->createUser());
    $this->assertQueryAccessResult([$node_1->id(), $node_2->id(), $node_3->id()], 'Non-members can see any unpublished nodes.');

    // This is actually a core issue, but for now unpublished nodes show up in
    // entity queries when there are no node grants defining modules.
    $this->setCurrentUser($account);
    $this->assertQueryAccessResult([$node_2->id()], 'Only the ungrouped unpublished node shows up.');
  }

  /**
   * Tests the viewing of own unpublished entities for members.
   */
  public function testMemberViewOwnUnpublishedAccess() {
    $account = $this->createUser();
    $node_1 = $this->createNode(['type' => 'page', 'status' => 0]);
    $node_2 = $this->createNode(['type' => 'page', 'status' => 0]);
    $node_3 = $this->createNode(['type' => 'page', 'status' => 0, 'uid' => $account->id()]);

    $this->groupTypeA->getMemberRole()->grantPermission('view own unpublished node_as_content:page entity')->save();
    $this->groupTypeB->getMemberRole()->grantPermission('view own unpublished node_as_content:page entity')->save();

    $group_a = $this->createGroup(['type' => $this->groupTypeA->id()]);
    $group_a->addContent($node_1, 'node_as_content:page');
    $group_a->addMember($this->getCurrentUser());
    $group_a->addMember($account);

    $group_b = $this->createGroup(['type' => $this->groupTypeB->id()]);
    $group_b->addContent($node_3, 'node_as_content:page');
    $group_b->addMember($this->getCurrentUser());
    $group_b->addMember($account);

    $this->assertQueryAccessResult([$node_1->id(), $node_2->id()], 'Members can see their own unpublished nodes.');

    $this->setCurrentUser($account);
    $this->assertQueryAccessResult([$node_2->id(), $node_3->id()], 'Members can see their own unpublished nodes.');

    // This is actually a core issue, but for now unpublished nodes show up in
    // entity queries when there are no node grants defining modules.
    $this->setCurrentUser($this->createUser());
    $this->assertQueryAccessResult([$node_2->id()], 'Only the ungrouped unpublished node shows up.');
  }

  /**
   * Tests the viewing of own unpublished entities for non-members.
   */
  public function testNonMemberViewOwnUnpublishedAccess() {
    $account = $this->createUser();
    $node_1 = $this->createNode(['type' => 'page', 'status' => 0]);
    $node_2 = $this->createNode(['type' => 'page', 'status' => 0]);
    $node_3 = $this->createNode(['type' => 'page', 'status' => 0, 'uid' => $account->id()]);

    $this->groupTypeA->getOutsiderRole()->grantPermission('view own unpublished node_as_content:page entity')->save();
    $this->groupTypeB->getOutsiderRole()->grantPermission('view own unpublished node_as_content:page entity')->save();

    $group_a = $this->createGroup(['type' => $this->groupTypeA->id()]);
    $group_a->addContent($node_1, 'node_as_content:page');
    $group_a->addMember($account);

    $group_b = $this->createGroup(['type' => $this->groupTypeB->id()]);
    $group_b->addContent($node_3, 'node_as_content:page');
    $group_b->addMember($account);

    $this->assertQueryAccessResult([$node_1->id(), $node_2->id()], 'Non-members can see their own unpublished nodes.');

    $this->setCurrentUser($this->createUser());
    $this->assertQueryAccessResult([$node_2->id()], 'Only the ungrouped unpublished node shows up.');

    // This is actually a core issue, but for now unpublished nodes show up in
    // entity queries when there are no node grants defining modules.
    $this->setCurrentUser($account);
    $this->assertQueryAccessResult([$node_2->id()], 'Only the ungrouped unpublished node shows up.');
  }

  /**
   * Tests the admin access for members.
   */
  public function testMemberAdminAccess() {
    $account = $this->createUser();
    $node_1 = $this->createNode(['type' => 'page']);
    $node_2 = $this->createNode(['type' => 'page', 'status' => 0]);
    $node_3 = $this->createNode(['type' => 'page']);
    $node_4 = $this->createNode(['type' => 'page', 'status' => 0]);
    $node_5 = $this->createNode(['type' => 'page', 'uid' => $account->id()]);
    $node_6 = $this->createNode(['type' => 'page', 'status' => 0, 'uid' => $account->id()]);

    $this->groupTypeA->getMemberRole()->grantPermission('administer node_as_content:page')->save();
    $this->groupTypeB->getMemberRole()->grantPermission('administer node_as_content:page')->save();

    $group_a = $this->createGroup(['type' => $this->groupTypeA->id()]);
    $group_a->addContent($node_3, 'node_as_content:page');
    $group_a->addContent($node_4, 'node_as_content:page');
    $group_a->addMember($this->getCurrentUser());

    $group_b = $this->createGroup(['type' => $this->groupTypeB->id()]);
    $group_b->addContent($node_5, 'node_as_content:page');
    $group_b->addContent($node_6, 'node_as_content:page');
    $group_b->addMember($this->getCurrentUser());

    $expected = [
      $node_1->id(),
      $node_2->id(),
      $node_3->id(),
      $node_4->id(),
      $node_5->id(),
      $node_6->id(),
    ];
    $this->assertQueryAccessResult($expected, 'Admin member can see anything regardless of owner or published status.');
  }

  /**
   * Tests the admin access for non-members.
   */
  public function testNonMemberAdminAccess() {
    $account = $this->createUser();
    $node_1 = $this->createNode(['type' => 'page']);
    $node_2 = $this->createNode(['type' => 'page', 'status' => 0]);
    $node_3 = $this->createNode(['type' => 'page']);
    $node_4 = $this->createNode(['type' => 'page', 'status' => 0]);
    $node_5 = $this->createNode(['type' => 'page', 'uid' => $account->id()]);
    $node_6 = $this->createNode(['type' => 'page', 'status' => 0, 'uid' => $account->id()]);

    $this->groupTypeA->getOutsiderRole()->grantPermission('administer node_as_content:page')->save();
    $this->groupTypeB->getOutsiderRole()->grantPermission('administer node_as_content:page')->save();

    $group_a = $this->createGroup(['type' => $this->groupTypeA->id()]);
    $group_a->addContent($node_3, 'node_as_content:page');
    $group_a->addContent($node_4, 'node_as_content:page');

    $group_b = $this->createGroup(['type' => $this->groupTypeB->id()]);
    $group_b->addContent($node_5, 'node_as_content:page');
    $group_b->addContent($node_6, 'node_as_content:page');

    $expected = [
      $node_1->id(),
      $node_2->id(),
      $node_3->id(),
      $node_4->id(),
      $node_5->id(),
      $node_6->id(),
    ];
    $this->assertQueryAccessResult($expected, 'Admin non-member can see anything regardless of owner or published status.');
  }

  /**
   * Tests that adding new group content clears caches.
   */
  public function testNewGroupContent() {
    $node_1 = $this->createNode(['type' => 'page']);
    $node_2 = $this->createNode(['type' => 'page']);
    $this->groupTypeA->getMemberRole()->grantPermission('view any node_as_content:page entity')->save();
    $group = $this->createGroup(['type' => $this->groupTypeA->id()]);

    $this->assertQueryAccessResult([$node_1->id(), $node_2->id()], 'Outsiders can see ungrouped nodes');

    // This should clear the cache.
    $group->addContent($node_1, 'node_as_content:page');
    $this->assertQueryAccessResult([$node_2->id()], 'Outsiders can see ungrouped nodes');
  }

  /**
   * Tests that adding new permissions clears caches.
   *
   * This is actually tested in the permission calculator, but added here also
   * for additional hardening. It does not really clear the cached conditions,
   * but rather return a different set as your user.group_permissions cache
   * context value changes.
   *
   * We will not test any further scenarios that trigger a change in your group
   * permissions as those are -as mentioned above- tested elsewhere. It just
   * seemed like a good idea to at least test one scenario here.
   */
  public function testNewPermission() {
    $node_1 = $this->createNode(['type' => 'page']);
    $node_2 = $this->createNode(['type' => 'page']);
    $group = $this->createGroup(['type' => $this->groupTypeA->id()]);
    $group->addContent($node_1, 'node_as_content:page');
    $group->addMember($this->getCurrentUser());

    $this->assertQueryAccessResult([$node_2->id()], 'Members can only see ungrouped nodes');

    // This should trigger a different set of conditions.
    $this->groupTypeA->getMemberRole()->grantPermission('view any node_as_content:page entity')->save();
    $this->assertQueryAccessResult([$node_1->id(), $node_2->id()], 'Outsiders can see grouped nodes');
  }

  /**
   * Asserts that the view returns the expected results.
   *
   * @param int[] $expected
   *   The expected node IDs.
   * @param $message
   *   The message for the assertion.
   */
  protected function assertQueryAccessResult($expected, $message) {
    $ids = $this->storage->getQuery()->execute();
    $this->assertEqualsCanonicalizing($expected, array_keys($ids), $message);

    $views_expected = [];
    foreach ($expected as $value) {
      $views_expected[] = ['nid' => $value];
    }
    $view = Views::getView('node_as_content');
    $view->execute();
    $this->assertIdenticalResultsetHelper($view, $views_expected, ['nid' => 'nid'], 'assertEqualsCanonicalizing', $message);
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
