<?php

namespace Drupal\Tests\group\Kernel;

use Drupal\views\Tests\ViewResultAssertionTrait;
use Drupal\views\Views;

/**
 * Tests that Group properly checks access for grouped entities.
 *
 * @todo Test operations other than view.
 *
 * @coversDefaultClass \Drupal\group\QueryAccess\EntityQueryAlter
 * @group group
 */
class EntityQueryAlterTest extends GroupKernelTestBase {

  use ViewResultAssertionTrait;

  /**
   * {@inheritdoc}
   */
  public static $modules = ['group_test_plugin', 'views'];

  /**
   * The grouped entity storage to use in testing.
   *
   * @var \Drupal\Core\Entity\ContentEntityStorageInterface
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
    $this->installEntitySchema('entity_test_with_owner');

    $this->storage = $this->entityTypeManager->getStorage('entity_test_with_owner');
    $this->groupTypeA = $this->createGroupType(['id' => 'foo', 'creator_membership' => FALSE]);
    $this->groupTypeB = $this->createGroupType(['id' => 'bar', 'creator_membership' => FALSE]);

    /** @var \Drupal\group\Entity\Storage\GroupContentTypeStorageInterface $storage */
    $storage = $this->entityTypeManager->getStorage('group_content_type');
    $storage->save($storage->createFromPlugin($this->groupTypeA, 'entity_test_as_content'));
    $storage->save($storage->createFromPlugin($this->groupTypeB, 'entity_test_as_content'));

    $this->setCurrentUser($this->createUser([], ['administer entity_test_with_owner content']));
  }

  /**
   * Tests that regular access checks still work.
   */
  public function testRegularAccess() {
    $entity_1 = $this->createTestEntity();
    $entity_2 = $this->createTestEntity();
    $this->assertQueryAccessResult([$entity_1->id(), $entity_2->id()], 'Regular test entity query access still works.');
  }

  /**
   * Tests that grouped test entities are properly hidden for members.
   */
  public function testGroupAccessWithoutPermission() {
    $entity_1 = $this->createTestEntity();
    $entity_2 = $this->createTestEntity();

    $group = $this->createGroup(['type' => $this->groupTypeA->id()]);
    $group->addContent($entity_1, 'entity_test_as_content');
    $group->addMember($this->getCurrentUser());

    $this->assertQueryAccessResult([$entity_2->id()], 'Only the ungrouped test entity shows up.');
  }

  /**
   * Tests that grouped test entities are properly hidden for non-members.
   */
  public function testNonMemberGroupAccessWithoutPermission() {
    $entity_1 = $this->createTestEntity();
    $entity_2 = $this->createTestEntity();

    $group = $this->createGroup(['type' => $this->groupTypeA->id()]);
    $group->addContent($entity_1, 'entity_test_as_content');

    $this->assertQueryAccessResult([$entity_2->id()], 'Only the ungrouped test entity shows up.');
  }

  /**
   * Tests that grouped test entities are visible to members.
   */
  public function testMemberGroupAccessWithPermission() {
    $entity_1 = $this->createTestEntity();
    $entity_2 = $this->createTestEntity();

    $this->groupTypeA->getMemberRole()->grantPermission('administer entity_test_as_content')->save();
    $group = $this->createGroup(['type' => $this->groupTypeA->id()]);
    $group->addContent($entity_1, 'entity_test_as_content');
    $group->addMember($this->getCurrentUser());

    $this->assertQueryAccessResult([$entity_1->id(), $entity_2->id()], 'Members can see grouped test entities');
  }

  /**
   * Tests that grouped test entities are visible to non-members.
   */
  public function testNonMemberGroupAccessWithPermission() {
    $entity_1 = $this->createTestEntity();
    $entity_2 = $this->createTestEntity();

    $this->groupTypeA->getOutsiderRole()->grantPermission('administer entity_test_as_content')->save();
    $group = $this->createGroup(['type' => $this->groupTypeA->id()]);
    $group->addContent($entity_1, 'entity_test_as_content');
    $this->createGroup(['type' => $this->groupTypeA->id()]);

    $this->assertQueryAccessResult([$entity_1->id(), $entity_2->id()], 'Outsiders can see grouped test entities');
  }

  /**
   * Tests the viewing of any entities for members.
   */
  public function testMemberViewAnyAccess() {
    $account = $this->createUser([], ['administer entity_test_with_owner content']);
    $entity_1 = $this->createTestEntity();
    $entity_2 = $this->createTestEntity();
    $entity_3 = $this->createTestEntity();

    $this->groupTypeA->getMemberRole()->grantPermission('view any entity_test_as_content entity')->save();
    $this->groupTypeB->getMemberRole()->grantPermission('view any entity_test_as_content entity')->save();

    $group_a = $this->createGroup(['type' => $this->groupTypeA->id()]);
    $group_a->addContent($entity_1, 'entity_test_as_content');
    $group_a->addMember($this->getCurrentUser());
    $group_a->addMember($account);

    $group_b = $this->createGroup(['type' => $this->groupTypeB->id()]);
    $group_b->addContent($entity_3, 'entity_test_as_content');
    $group_b->addMember($this->getCurrentUser());
    $group_b->addMember($account);

    $this->assertQueryAccessResult([$entity_1->id(), $entity_2->id(), $entity_3->id()], 'Members can see any test entities.');

    $this->setCurrentUser($account);
    $this->assertQueryAccessResult([$entity_1->id(), $entity_2->id(), $entity_3->id()], 'Members can see any test entities.');

    $this->setCurrentUser($this->createUser([], ['administer entity_test_with_owner content']));
    $this->assertQueryAccessResult([$entity_2->id()], 'Only the ungrouped test entity shows up.');
  }

  /**
   * Tests the viewing of any entities for non-members.
   */
  public function testNonMemberViewAnyAccess() {
    $account = $this->createUser([], ['administer entity_test_with_owner content']);
    $entity_1 = $this->createTestEntity();
    $entity_2 = $this->createTestEntity();
    $entity_3 = $this->createTestEntity();

    $this->groupTypeA->getOutsiderRole()->grantPermission('view any entity_test_as_content entity')->save();
    $this->groupTypeB->getOutsiderRole()->grantPermission('view any entity_test_as_content entity')->save();

    $group_a = $this->createGroup(['type' => $this->groupTypeA->id()]);
    $group_a->addContent($entity_1, 'entity_test_as_content');
    $group_a->addMember($account);

    $group_b = $this->createGroup(['type' => $this->groupTypeB->id()]);
    $group_b->addContent($entity_3, 'entity_test_as_content');
    $group_b->addMember($account);

    $this->assertQueryAccessResult([$entity_1->id(), $entity_2->id(), $entity_3->id()], 'Non-members can see any test entities.');

    $this->setCurrentUser($this->createUser([], ['administer entity_test_with_owner content']));
    $this->assertQueryAccessResult([$entity_1->id(), $entity_2->id(), $entity_3->id()], 'Non-members can see any test entities.');

    $this->setCurrentUser($account);
    $this->assertQueryAccessResult([$entity_2->id()], 'Only the ungrouped test entity shows up.');
  }

  /**
   * Tests the viewing of own entities for members.
   */
  public function testMemberViewOwnAccess() {
    $account = $this->createUser([], ['administer entity_test_with_owner content']);
    $entity_1 = $this->createTestEntity();
    $entity_2 = $this->createTestEntity();
    $entity_3 = $this->createTestEntity(['uid' => $account->id()]);

    $this->groupTypeA->getMemberRole()->grantPermission('view own entity_test_as_content entity')->save();
    $this->groupTypeB->getMemberRole()->grantPermission('view own entity_test_as_content entity')->save();

    $group_a = $this->createGroup(['type' => $this->groupTypeA->id()]);
    $group_a->addContent($entity_1, 'entity_test_as_content');
    $group_a->addMember($this->getCurrentUser());
    $group_a->addMember($account);

    $group_b = $this->createGroup(['type' => $this->groupTypeB->id()]);
    $group_b->addContent($entity_3, 'entity_test_as_content');
    $group_b->addMember($this->getCurrentUser());
    $group_b->addMember($account);

    $this->assertQueryAccessResult([$entity_1->id(), $entity_2->id()], 'Members can see their own test entities.');

    $this->setCurrentUser($account);
    $this->assertQueryAccessResult([$entity_2->id(), $entity_3->id()], 'Members can see their own test entities.');

    $this->setCurrentUser($this->createUser());
    $this->assertQueryAccessResult([$entity_2->id()], 'Only the ungrouped test entity shows up.');
  }

  /**
   * Tests the viewing of own entities for non-members.
   */
  public function testNonMemberViewOwnAccess() {
    $account = $this->createUser([], ['administer entity_test_with_owner content']);
    $entity_1 = $this->createTestEntity();
    $entity_2 = $this->createTestEntity();
    $entity_3 = $this->createTestEntity(['uid' => $account->id()]);

    $this->groupTypeA->getOutsiderRole()->grantPermission('view own entity_test_as_content entity')->save();
    $this->groupTypeB->getOutsiderRole()->grantPermission('view own entity_test_as_content entity')->save();

    $group_a = $this->createGroup(['type' => $this->groupTypeA->id()]);
    $group_a->addContent($entity_1, 'entity_test_as_content');
    $group_a->addMember($account);

    $group_b = $this->createGroup(['type' => $this->groupTypeB->id()]);
    $group_b->addContent($entity_3, 'entity_test_as_content');
    $group_b->addMember($account);

    $this->assertQueryAccessResult([$entity_1->id(), $entity_2->id()], 'Non-members can see their own test entities.');

    $this->setCurrentUser($this->createUser());
    $this->assertQueryAccessResult([$entity_2->id()], 'Non-members cannot see test entities they do not own.');

    $this->setCurrentUser($account);
    $this->assertQueryAccessResult([$entity_2->id()], 'Only the ungrouped test entity shows up.');
  }
  
  /**
   * Tests that adding new group content clears caches.
   */
  public function testNewGroupContent() {
    $entity_1 = $this->createTestEntity();
    $entity_2 = $this->createTestEntity();
    $this->groupTypeA->getMemberRole()->grantPermission('view any entity_test_as_content entity')->save();
    $group = $this->createGroup(['type' => $this->groupTypeA->id()]);

    $this->assertQueryAccessResult([$entity_1->id(), $entity_2->id()], 'Outsiders can see ungrouped test entities');

    // This should clear the cache.
    $group->addContent($entity_1, 'entity_test_as_content');

    $this->assertQueryAccessResult([$entity_2->id()], 'Outsiders can see ungrouped test entities');
  }

  /**
   * Tests that adding new permissions clears caches.
   *
   * This is actually tested in the permission calculator, but added here also
   * for additional hardening. It does not really clear the cached conditions,
   * but rather return a different set as your test entity.group_permissions cache
   * context value changes.
   *
   * We will not test any further scenarios that trigger a change in your group
   * permissions as those are -as mentioned above- tested elsewhere. It just
   * seemed like a good idea to at least test one scenario here.
   */
  public function testNewPermission() {
    $entity_1 = $this->createTestEntity();
    $entity_2 = $this->createTestEntity();
    $group = $this->createGroup(['type' => $this->groupTypeA->id()]);
    $group->addContent($entity_1, 'entity_test_as_content');
    $group->addMember($this->getCurrentUser());

    $this->assertQueryAccessResult([$entity_2->id()], 'Members can only see ungrouped test entities');

    // This should trigger a different set of conditions.
    $this->groupTypeA->getMemberRole()->grantPermission('view any entity_test_as_content entity')->save();

    $this->assertQueryAccessResult([$entity_1->id(), $entity_2->id()], 'Outsiders can see grouped test entities');
  }

  /**
   * Asserts that the view returns the expected results.
   *
   * @param int[] $expected
   *   The expected test entity IDs.
   * @param $message
   *   The message for the assertion.
   */
  protected function assertQueryAccessResult($expected, $message) {
    $ids = $this->storage->getQuery()->execute();
    $this->assertEqualsCanonicalizing($expected, array_keys($ids), $message);

    $views_expected = [];
    foreach ($expected as $value) {
      $views_expected[] = ['id' => $value];
    }
    $view = Views::getView('entity_test_as_content');
    $view->execute();
    $this->assertIdenticalResultsetHelper($view, $views_expected, ['id' => 'id'], 'assertEqualsCanonicalizing', $message);
  }

  /**
   * Creates a test entity.
   *
   * @param array $values
   *   (optional) The values used to create the entity.
   *
   * @return \Drupal\entity_test\Entity\EntityTest
   *   The created test entity entity.
   */
  protected function createTestEntity(array $values = []) {
    $test_entity = $this->storage->create($values + [
      'name' => $this->randomString(),
    ]);
    $test_entity->enforceIsNew();
    $this->storage->save($test_entity);
    return $test_entity;
  }

}
