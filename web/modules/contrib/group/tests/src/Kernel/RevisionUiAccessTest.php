<?php

namespace Drupal\Tests\group\Kernel;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Url;
use Drupal\group\Entity\GroupInterface;
use Symfony\Cmf\Component\Routing\RouteObjectInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Tests the revision UI access for groups.
 *
 * @covers \Drupal\group\Entity\Access\GroupRevisionCheck
 * @group group
 */
class RevisionUiAccessTest extends GroupKernelTestBase {

  /**
   * The access manager.
   *
   * @var \Drupal\Core\Access\AccessManagerInterface
   */
  protected $accessManager;

  /**
   * The route provider.
   *
   * @var \Drupal\Core\Routing\RouteProviderInterface
   */
  protected $routeProvider;

  /**
   * The group type to run this test on.
   *
   * @var \Drupal\group\Entity\GroupTypeInterface
   */
  protected $groupType;

  /**
   * The group admin role.
   *
   * @var \Drupal\group\Entity\GroupRoleInterface
   */
  protected $adminRole;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->accessManager = $this->container->get('access_manager');
    $this->routeProvider = $this->container->get('router.route_provider');
    $this->groupType = $this->createGroupType([
      'id' => 'revision_test',
      'creator_membership' => FALSE,
    ]);

    $this->adminRole = $this->entityTypeManager->getStorage('group_role')->create([
      'id' => 'revision_test-admin',
      'label' => 'Revision admin',
      'weight' => 0,
      'group_type' => $this->groupType->id(),
    ]);
    $this->adminRole->grantPermission('administer group')->save();
  }

  /**
   * Tests access to the overview page.
   *
   * @dataProvider overviewAccessProvider
   */
  public function testOverviewAccess($outsider_permissions, $member_permissions, $outsider_access, $member_access, $admin_access, $new_revision, $extra_revision, $message) {
    $outsider = $this->createUser();
    $member = $this->createUser();
    $admin = $this->createUser();

    $this->groupType->getOutsiderRole()
      ->grantPermissions($outsider_permissions)
      ->save();

    $this->groupType->getMemberRole()
      ->grantPermissions($member_permissions)
      ->save();

    $group = $this->createGroup(['type' => $this->groupType->id()]);
    $group->addMember($member);
    $group->addMember($admin, ['group_roles' => [$this->adminRole->id()]]);

    if ($extra_revision) {
      $group->setNewRevision(TRUE);
      $group->isDefaultRevision(TRUE);
      $group->save();
    }

    if (!$new_revision) {
      $this->groupType->set('new_revision', $new_revision)->save();
    }

    $request = $this->createRequest('entity.group.version_history', $this->reloadEntity($group));
    $this->assertSame($outsider_access, $this->accessManager->checkRequest($request, $outsider), $message);
    $this->assertSame($member_access, $this->accessManager->checkRequest($request, $member), $message);
    $this->assertSame($admin_access, $this->accessManager->checkRequest($request, $admin), $message);
  }

  /**
   * Data provider for testOverviewAccess().
   *
   * @return array
   *   A list of testOverviewAccess method arguments.
   */
  public function overviewAccessProvider() {
    $cases = [];

    $cases['view-one-rev-no-new-rev'] = [
      ['view group'],
      ['view group', 'view group revisions'],
      FALSE,
      FALSE,
      FALSE,
      FALSE,
      FALSE,
      'Checking access to revision overview when there is one revision and new revisions are not created automatically',
    ];

    $cases['view-one-rev-new-rev'] = [
      ['view group'],
      ['view group', 'view group revisions'],
      FALSE,
      TRUE,
      TRUE,
      TRUE,
      FALSE,
      'Checking access to revision overview when there is one revision and new revisions are created automatically',
    ];

    $cases['view-multi-rev-no-new-rev'] = [
      ['view group'],
      ['view group', 'view group revisions'],
      FALSE,
      TRUE,
      TRUE,
      FALSE,
      TRUE,
      'Checking access to revision overview when there are multiple revisions and new revisions are not created automatically',
    ];

    $cases['view-multi-rev-new-rev'] = [
      ['view group'],
      ['view group', 'view group revisions'],
      FALSE,
      TRUE,
      TRUE,
      TRUE,
      TRUE,
      'Checking access to revision overview when there are multiple revisions and new revisions are created automatically',
    ];

    $cases['no-view-one-rev-new-rev'] = [
      [],
      ['view group revisions'],
      FALSE,
      FALSE,
      TRUE,
      TRUE,
      FALSE,
      'Checking access to revision overview when there is one revision and new revisions are created automatically, but the user has no view access',
    ];

    $cases['no-view-multi-rev-new-rev'] = [
      [],
      ['view group revisions'],
      FALSE,
      FALSE,
      TRUE,
      TRUE,
      TRUE,
      'Checking access to revision overview when there are multiple revisions and new revisions are created automatically, but the user has no view access',
    ];

    return $cases;
  }

  /**
   * Tests access to the view page.
   *
   * @dataProvider viewAccessProvider
   */
  public function testViewAccess($outsider_permissions, $member_permissions, $outsider_access, $member_access, $admin_access, $new_revision, $extra_revision, $pass_default_revision, $revision_published, $extra_revision_published, $message) {
    $outsider = $this->createUser();
    $member = $this->createUser();
    $admin = $this->createUser();

    $this->groupType->getOutsiderRole()
      ->grantPermissions($outsider_permissions)
      ->save();

    $this->groupType->getMemberRole()
      ->grantPermissions($member_permissions)
      ->save();

    $group = $this->createGroup(['type' => $this->groupType->id(), 'status' => $revision_published]);
    $group->addMember($member);
    $group->addMember($admin, ['group_roles' => [$this->adminRole->id()]]);

    $group_revision = clone $group;
    if ($extra_revision) {
      if ($extra_revision_published) {
        $group_revision->setPublished();
      }
      else {
        $group_revision->setUnpublished();
      }
      $group_revision->setNewRevision(TRUE);
      $group_revision->isDefaultRevision(TRUE);
      $group_revision->save();

      if (!$pass_default_revision) {
        $group_revision = $group;
      }
    }

    if (!$new_revision) {
      $this->groupType->set('new_revision', $new_revision)->save();
    }

    $request = $this->createRequest('entity.group.revision', $this->reloadEntity($group), $this->reloadRevision($group_revision));
    $this->assertSame($outsider_access, $this->accessManager->checkRequest($request, $outsider), $message);
    $this->assertSame($member_access, $this->accessManager->checkRequest($request, $member), $message);
    $this->assertSame($admin_access, $this->accessManager->checkRequest($request, $admin), $message);
  }

  /**
   * Data provider for testViewAccess().
   *
   * @return array
   *   A list of testViewAccess method arguments.
   */
  public function viewAccessProvider() {
    $cases = [];

    $cases['view-one-rev-no-new-rev'] = [
      ['view group'],
      ['view group', 'view group revisions'],
      FALSE,
      FALSE,
      FALSE,
      FALSE,
      FALSE,
      FALSE,
      TRUE,
      TRUE,
      'Checking access to published revision viewing when there is one revision and new revisions are not created automatically',
    ];

    $cases['view-one-rev-new-rev'] = [
      ['view group'],
      ['view group', 'view group revisions'],
      FALSE,
      TRUE,
      TRUE,
      TRUE,
      FALSE,
      FALSE,
      TRUE,
      TRUE,
      'Checking access to published revision viewing when there is one revision and new revisions are created automatically',
    ];

    $cases['view-multi-rev-no-new-rev-default'] = [
      ['view group'],
      ['view group', 'view group revisions'],
      FALSE,
      TRUE,
      TRUE,
      FALSE,
      TRUE,
      TRUE,
      TRUE,
      TRUE,
      'Checking access to default published revision viewing when there are multiple revisions and new revisions are not created automatically',
    ];

    $cases['view-multi-rev-new-rev-default'] = [
      ['view group'],
      ['view group', 'view group revisions'],
      FALSE,
      TRUE,
      TRUE,
      TRUE,
      TRUE,
      TRUE,
      TRUE,
      TRUE,
      'Checking access to default published revision viewing when there are multiple revisions and new revisions are created automatically',
    ];

    $cases['view-multi-rev-no-new-rev-non-default'] = [
      ['view group'],
      ['view group', 'view group revisions'],
      FALSE,
      TRUE,
      TRUE,
      FALSE,
      TRUE,
      FALSE,
      TRUE,
      TRUE,
      'Checking access to non-default published revision viewing when there are multiple revisions and new revisions are not created automatically',
    ];

    $cases['view-multi-rev-new-rev-non-default'] = [
      ['view group'],
      ['view group', 'view group revisions'],
      FALSE,
      TRUE,
      TRUE,
      TRUE,
      TRUE,
      FALSE,
      TRUE,
      TRUE,
      'Checking access to non-default published revision viewing when there are multiple revisions and new revisions are created automatically',
    ];

    $cases['no-view-one-rev-new-rev-default'] = [
      [],
      ['view group revisions'],
      FALSE,
      FALSE,
      TRUE,
      TRUE,
      FALSE,
      TRUE,
      TRUE,
      TRUE,
      'Checking access to default published revision viewing when there is one revision and new revisions are created automatically, but the user has no view access',
    ];

    $cases['no-view-multi-rev-new-rev-default'] = [
      [],
      ['view group revisions'],
      FALSE,
      FALSE,
      TRUE,
      TRUE,
      TRUE,
      TRUE,
      TRUE,
      TRUE,
      'Checking access to default published revision viewing when there are multiple revisions and new revisions are created automatically, but the user has no view access',
    ];

    $cases['no-view-one-rev-new-rev-non-default'] = [
      [],
      ['view group revisions'],
      FALSE,
      FALSE,
      TRUE,
      TRUE,
      FALSE,
      FALSE,
      TRUE,
      TRUE,
      'Checking access to non-default published revision viewing when there is one revision and new revisions are created automatically, but the user has no view access',
    ];

    $cases['no-view-multi-rev-new-rev-non-default'] = [
      [],
      ['view group revisions'],
      FALSE,
      FALSE,
      TRUE,
      TRUE,
      TRUE,
      FALSE,
      TRUE,
      TRUE,
      'Checking access to non-default published revision viewing when there are multiple revisions and new revisions are created automatically, but the user has no view access',
    ];

    $cases['view-unpub-one-rev-no-new-rev'] = [
      ['view any unpublished group'],
      ['view any unpublished group', 'view group revisions'],
      FALSE,
      FALSE,
      FALSE,
      FALSE,
      FALSE,
      FALSE,
      FALSE,
      FALSE,
      'Checking access to unpublished revision viewing when there is one revision and new revisions are not created automatically',
    ];

    $cases['view-unpub-one-rev-new-rev'] = [
      ['view any unpublished group'],
      ['view any unpublished group', 'view group revisions'],
      FALSE,
      TRUE,
      TRUE,
      TRUE,
      FALSE,
      FALSE,
      FALSE,
      FALSE,
      'Checking access to unpublished revision viewing when there is one revision and new revisions are created automatically',
    ];

    $cases['view-unpub-multi-rev-no-new-rev-default'] = [
      ['view any unpublished group'],
      ['view any unpublished group', 'view group revisions'],
      FALSE,
      TRUE,
      TRUE,
      FALSE,
      TRUE,
      TRUE,
      FALSE,
      FALSE,
      'Checking access to default unpublished revision viewing when there are multiple revisions and new revisions are not created automatically',
    ];

    $cases['view-unpub-multi-rev-new-rev-default'] = [
      ['view any unpublished group'],
      ['view any unpublished group', 'view group revisions'],
      FALSE,
      TRUE,
      TRUE,
      TRUE,
      TRUE,
      TRUE,
      FALSE,
      FALSE,
      'Checking access to default unpublished revision viewing when there are multiple revisions and new revisions are created automatically',
    ];

    $cases['view-unpub-multi-rev-no-new-rev-non-default'] = [
      ['view any unpublished group'],
      ['view any unpublished group', 'view group revisions'],
      FALSE,
      TRUE,
      TRUE,
      FALSE,
      TRUE,
      FALSE,
      FALSE,
      FALSE,
      'Checking access to non-default unpublished revision viewing when there are multiple revisions and new revisions are not created automatically',
    ];

    $cases['view-unpub-multi-rev-new-rev-non-default'] = [
      ['view any unpublished group'],
      ['view any unpublished group', 'view group revisions'],
      FALSE,
      TRUE,
      TRUE,
      TRUE,
      TRUE,
      FALSE,
      FALSE,
      FALSE,
      'Checking access to non-default unpublished revision viewing when there are multiple revisions and new revisions are created automatically',
    ];

    $cases['no-view-unpub-one-rev-new-rev-default'] = [
      [],
      ['view group revisions'],
      FALSE,
      FALSE,
      TRUE,
      TRUE,
      FALSE,
      TRUE,
      FALSE,
      FALSE,
      'Checking access to default unpublished revision viewing when there is one revision and new revisions are created automatically, but the user has no view access',
    ];

    $cases['no-view-unpub-multi-rev-new-rev-default'] = [
      [],
      ['view group revisions'],
      FALSE,
      FALSE,
      TRUE,
      TRUE,
      TRUE,
      TRUE,
      FALSE,
      FALSE,
      'Checking access to default unpublished revision viewing when there are multiple revisions and new revisions are created automatically, but the user has no view access',
    ];

    $cases['no-view-unpub-one-rev-new-rev-non-default'] = [
      [],
      ['view group revisions'],
      FALSE,
      FALSE,
      TRUE,
      TRUE,
      FALSE,
      FALSE,
      FALSE,
      FALSE,
      'Checking access to non-default unpublished revision viewing when there is one revision and new revisions are created automatically, but the user has no view access',
    ];

    $cases['no-view-unpub-multi-rev-new-rev-non-default'] = [
      [],
      ['view group revisions'],
      FALSE,
      FALSE,
      TRUE,
      TRUE,
      TRUE,
      FALSE,
      FALSE,
      FALSE,
      'Checking access to non-default unpublished revision viewing when there are multiple revisions and new revisions are created automatically, but the user has no view access',
    ];

    // Mixed revisions are where the original one is unpublished and the default
    // one is published. This proves you need both 'view published' and 'view
    // unpublished' access.
    $cases['view-mixed-default'] = [
      ['view group', 'view any unpublished group'],
      ['view group', 'view any unpublished group', 'view group revisions'],
      FALSE,
      TRUE,
      TRUE,
      TRUE,
      TRUE,
      TRUE,
      FALSE,
      TRUE,
      'Checking access to default mixed revision viewing',
    ];

    $cases['view-mixed-non-default'] = [
      ['view group', 'view any unpublished group'],
      ['view group', 'view any unpublished group', 'view group revisions'],
      FALSE,
      TRUE,
      TRUE,
      TRUE,
      TRUE,
      FALSE,
      FALSE,
      TRUE,
      'Checking access to non-default mixed revision viewing',
    ];

    $cases['no-view-mixed-default'] = [
      ['view any unpublished group'],
      ['view any unpublished group', 'view group revisions'],
      FALSE,
      FALSE,
      TRUE,
      TRUE,
      TRUE,
      TRUE,
      FALSE,
      TRUE,
      'Checking access to default mixed revision viewing when the user has no view access',
    ];

    $cases['no-view-mixed-non-default'] = [
      ['view any unpublished group'],
      ['view any unpublished group', 'view group revisions'],
      FALSE,
      FALSE,
      TRUE,
      TRUE,
      TRUE,
      FALSE,
      FALSE,
      TRUE,
      'Checking access to non-default mixed revision viewing when the user has no view access',
    ];

    $cases['no-view-unpub-mixed-default'] = [
      ['view group'],
      ['view group', 'view group revisions'],
      FALSE,
      TRUE,
      TRUE,
      TRUE,
      TRUE,
      TRUE,
      FALSE,
      TRUE,
      'Checking access to default mixed revision viewing when the user has no view unpublished access',
    ];

    $cases['no-view-unpub-mixed-non-default'] = [
      ['view group'],
      ['view group', 'view group revisions'],
      FALSE,
      FALSE,
      TRUE,
      TRUE,
      TRUE,
      FALSE,
      FALSE,
      TRUE,
      'Checking access to non-default mixed revision viewing when the user has no view unpublished access',
    ];

    return $cases;
  }

  /**
   * Tests access to the update (revert) or delete form.
   *
   * @dataProvider updateDeleteAccessProvider
   */
  public function testUpdateDeleteAccess($route_name, $outsider_permissions, $member_permissions, $outsider_access, $member_access, $admin_access, $pass_default_revision, $message) {
    $outsider = $this->createUser();
    $member = $this->createUser();
    $admin = $this->createUser();

    $this->groupType->getOutsiderRole()
      ->grantPermissions($outsider_permissions)
      ->save();

    $this->groupType->getMemberRole()
      ->grantPermissions($member_permissions)
      ->save();

    $group = $this->createGroup(['type' => $this->groupType->id()]);
    $group->addMember($member);
    $group->addMember($admin, ['group_roles' => [$this->adminRole->id()]]);

    $group_revision = clone $group;
    $group_revision->setNewRevision(TRUE);
    $group_revision->isDefaultRevision(TRUE);
    $group_revision->save();

    if (!$pass_default_revision) {
      $group_revision = $group;
    }

    $request = $this->createRequest($route_name, $this->reloadEntity($group), $this->reloadRevision($group_revision));
    $this->assertSame($outsider_access, $this->accessManager->checkRequest($request, $outsider), $message);
    $this->assertSame($member_access, $this->accessManager->checkRequest($request, $member), $message);
    $this->assertSame($admin_access, $this->accessManager->checkRequest($request, $admin), $message);
  }

  /**
   * Data provider for testUpdateDeleteAccess().
   *
   * @return array
   *   A list of testUpdateDeleteAccess method arguments.
   */
  public function updateDeleteAccessProvider() {
    $cases = [];

    $cases['edit-rev-default'] = [
      'entity.group.revision_revert_form',
      ['edit group'],
      ['edit group', 'revert group revisions'],
      FALSE,
      FALSE,
      FALSE,
      TRUE,
      'Checking access to default revision reverting',
    ];

    $cases['edit-rev-non-default'] = [
      'entity.group.revision_revert_form',
      ['edit group'],
      ['edit group', 'revert group revisions'],
      FALSE,
      TRUE,
      TRUE,
      FALSE,
      'Checking access to non-default revision reverting',
    ];

    $cases['no-edit-rev-default'] = [
      'entity.group.revision_revert_form',
      [],
      ['revert group revisions'],
      FALSE,
      FALSE,
      FALSE,
      TRUE,
      'Checking access to default revision reverting, but the user has no update access',
    ];

    $cases['no-edit-rev-non-default'] = [
      'entity.group.revision_revert_form',
      [],
      ['revert group revisions'],
      FALSE,
      FALSE,
      TRUE,
      FALSE,
      'Checking access to non-default revision reverting, but the user has no update access',
    ];

    $cases['delete-rev-default'] = [
      'entity.group.revision_delete_form',
      ['delete group'],
      ['delete group', 'delete group revisions'],
      FALSE,
      FALSE,
      FALSE,
      TRUE,
      'Checking access to default revision deleting',
    ];

    $cases['delete-rev-non-default'] = [
      'entity.group.revision_delete_form',
      ['delete group'],
      ['delete group', 'delete group revisions'],
      FALSE,
      TRUE,
      TRUE,
      FALSE,
      'Checking access to non-default revision deleting',
    ];

    $cases['no-delete-rev-default'] = [
      'entity.group.revision_delete_form',
      [],
      ['delete group revisions'],
      FALSE,
      FALSE,
      FALSE,
      TRUE,
      'Checking access to default revision deleting, but the user has no delete access',
    ];

    $cases['no-delete-rev-non-default'] = [
      'entity.group.revision_delete_form',
      [],
      ['delete group revisions'],
      FALSE,
      FALSE,
      TRUE,
      FALSE,
      'Checking access to non-default revision deleting, but the user has no delete access',
    ];

    return $cases;
  }

  /**
   * Creates a request for a specific route.
   *
   * @param string $route_name
   *   The route name.
   * @param \Drupal\group\Entity\GroupInterface $group
   *   The group.
   * @param \Drupal\group\Entity\GroupInterface|null $group_revision
   *   (optional) The group revision.
   *
   * @return \Symfony\Component\HttpFoundation\Request
   *   The request.
   */
  protected function createRequest($route_name, GroupInterface $group, GroupInterface $group_revision = NULL) {
    $params = ['group' => $group->id()];
    $attributes = ['group' => $group,];
    if ($group_revision) {
      $params['group_revision'] = $group_revision->getRevisionId();
      $attributes['group_revision'] = $group_revision;
    }

    $attributes[RouteObjectInterface::ROUTE_OBJECT] = $this->routeProvider->getRouteByName($route_name);
    $request = Request::create(Url::fromRoute($route_name, $params)->toString());
    $request->attributes->add($attributes);

    // Push the request to the request stack so `current_route_match` works.
    $this->container->get('request_stack')->push($request);
    return $request;
  }

  /**
   * Counts the number of revisions in the default language.
   *
   * @param \Drupal\group\Entity\GroupInterface $group
   *   The group.
   *
   * @return int
   *   The number of revisions in the default language.
   */
  protected function countDefaultLanguageRevisions(GroupInterface $group) {
    return (int) $this->entityTypeManager->getStorage('group')
      ->getQuery()
      ->allRevisions()
      ->condition('id', $group->id())
      ->condition('default_langcode', 1)
      ->count()
      ->execute();
  }

  /**
   * Reloads the given entity revision from the storage and returns it.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity revision to be reloaded.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *   The reloaded entity revision.
   */
  protected function reloadRevision(ContentEntityInterface $entity) {
    $controller = $this->entityTypeManager->getStorage($entity->getEntityTypeId());
    $controller->resetCache([$entity->id()]);
    return $controller->loadRevision($entity->getRevisionId());
  }

}
