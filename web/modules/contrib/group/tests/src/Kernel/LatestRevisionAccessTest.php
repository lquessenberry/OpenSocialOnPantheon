<?php

namespace Drupal\Tests\group\Kernel;

use Drupal\Core\Url;
use Drupal\group\Entity\GroupInterface;
use Drupal\Tests\content_moderation\Traits\ContentModerationTestTrait;
use Symfony\Cmf\Component\Routing\RouteObjectInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Tests the latest revision access for groups.
 *
 * @group group
 */
class LatestRevisionAccessTest extends GroupKernelTestBase {

  use ContentModerationTestTrait;

  /**
   * {@inheritdoc}
   */
  public static $modules = ['workflows', 'content_moderation'];

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
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->installConfig(['content_moderation', 'workflows']);
    $this->installEntitySchema('workflow');
    $this->installEntitySchema('content_moderation_state');

    $this->accessManager = $this->container->get('access_manager');
    $this->routeProvider = $this->container->get('router.route_provider');
    $this->groupType = $this->createGroupType([
      'id' => 'revision_test',
      'creator_membership' => FALSE,
    ]);

    $workflow = $this->createEditorialWorkflow();
    $this->addEntityTypeAndBundleToWorkflow($workflow, 'group', $this->groupType->id());
  }

  /**
   * Tests access to the revision tab.
   *
   * @todo Rewrite like RevisionUiAccessTest. Data providers means less noise
   *   from resetting code.
   */
  public function testAccess() {
    $moderation_info = $this->container->get('content_moderation.moderation_information');

    // Create two accounts to test with.
    $user_with_access = $this->createUser();
    $user_without_access = $this->createUser();

    // Set up the initial permissions for the accounts.
    $this->groupType->getOutsiderRole()
      ->grantPermission('view group')
      ->save();

    $this->groupType->getMemberRole()
      ->grantPermissions([
        'view group',
        'view any unpublished group',
        'view latest group version',
      ])
      ->save();

    // Create a group with no pending revisions.
    $group = $this->createGroup([
      'type' => $this->groupType->id(),
      'moderation_state' => 'published',
    ]);
    $this->assertFalse($moderation_info->hasPendingRevision($group));

    // Make sure the permissive account is a member.
    $group->addMember($user_with_access);

    // Check access when there is no pending revision.
    $request = $this->createRequest($group);
    $this->assertFalse($this->accessManager->checkRequest($request, $user_with_access), 'An account with sufficient permissions has no access if there is no pending revision.');
    $this->assertFalse($this->accessManager->checkRequest($request, $user_without_access), 'An account with insufficient permissions has no access if there is no pending revision.');

    // Verify that even admins can't see the revision page if there are none.
    $admin = $this->createUser();
    $this->entityTypeManager->getStorage('group_role')
      ->create([
        'id' => 'revision_test-admin',
        'label' => 'Revision admin',
        'weight' => 0,
        'group_type' => $this->groupType->id(),
      ])
      ->grantPermission('administer group')
      ->save();
    $group->addMember($admin, ['group_roles' => ['revision_test-admin']]);
    $this->assertFalse($this->accessManager->checkRequest($request, $admin), 'An admin has no access if there is no pending revision.');

    // Create a pending revision of the original group.
    $group->moderation_state = 'draft';
    $group->setNewRevision(TRUE);
    $group->isDefaultRevision(FALSE);
    $group->save();

    // Use a fresh copy of the group for new requests because Drupal otherwise
    // won't find the pending revision properly.
    $group = $this->reloadEntity($group);
    $this->assertTrue($moderation_info->hasPendingRevision($group));

    // Check access when there is a pending revision.
    $request = $this->createRequest($group);
    $this->assertTrue($this->accessManager->checkRequest($request, $user_with_access), 'An account with sufficient permissions has access if there is a pending revision.');
    $this->assertFalse($this->accessManager->checkRequest($request, $user_without_access), 'An account with insufficient permissions has no access if there is a pending revision.');

    // Now remove the ability to view unpublished groups and try again.
    $this->groupType
      ->getMemberRole()
      ->revokePermission('view any unpublished group')
      ->save();

    $request = $this->createRequest($group);
    $this->entityTypeManager->getAccessControlHandler('group')->resetCache();
    $this->assertFalse($this->accessManager->checkRequest($request, $user_with_access), 'Removing the ability to view unpublished groups denies access to pending revisions.');

    // Grant back the view unpublished access but revoke revision access.
    $this->groupType->getMemberRole()
      ->grantPermission('view any unpublished group')
      ->revokePermission('view latest group version')
      ->save();

    $request = $this->createRequest($group);
    $this->entityTypeManager->getAccessControlHandler('group')->resetCache();
    $this->assertFalse($this->accessManager->checkRequest($request, $user_with_access), 'Removing the ability to view revisions denies access to pending revisions.');

    // Test that the admin permission also works.
    $this->groupType->getMemberRole()
      ->revokePermission('view any unpublished group')
      ->grantPermission('administer group')
      ->save();

    $request = $this->createRequest($group);
    $this->entityTypeManager->getAccessControlHandler('group')->resetCache();
    $this->assertTrue($this->accessManager->checkRequest($request, $user_with_access), 'A group admin can see pending revisions.');
  }

  /**
   * Creates a request for the group revision overview.
   *
   * @param \Drupal\group\Entity\GroupInterface $group
   *   The group.
   *
   * @return \Symfony\Component\HttpFoundation\Request
   *   The request.
   */
  protected function createRequest(GroupInterface $group) {
    $url = Url::fromRoute('entity.group.latest_version', ['group' => $group->id()]);
    $route = $this->routeProvider->getRouteByName($url->getRouteName());

    $request = Request::create($url->toString());
    $request->attributes->add([
      RouteObjectInterface::ROUTE_OBJECT => $route,
      'group' => $group,
    ]);

    // Push the request to the request stack so `current_route_match` works.
    $this->container->get('request_stack')->push($request);

    return $request;
  }

}
