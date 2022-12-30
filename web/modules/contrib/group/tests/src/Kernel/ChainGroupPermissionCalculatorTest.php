<?php

namespace Drupal\Tests\group\Kernel;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Session\AnonymousUserSession;
use Drupal\group\Access\CalculatedGroupPermissions;
use Drupal\group\Access\CalculatedGroupPermissionsInterface;
use Drupal\group\Access\RefinableCalculatedGroupPermissions;

/**
 * Tests the calculation of group permissions.
 *
 * This also inherently tests the following calculators:
 * - \Drupal\group\Access\DefaultGroupPermissionCalculator
 * - \Drupal\group\Access\SynchronizedGroupPermissionCalculator
 *
 * @todo Individually test the above calculators?
 *
 * @coversDefaultClass \Drupal\group\Access\ChainGroupPermissionCalculator
 * @group group
 */
class ChainGroupPermissionCalculatorTest extends GroupKernelTestBase {

  /**
   * The group permissions hash generator service.
   *
   * @var \Drupal\group\Access\ChainGroupPermissionCalculatorInterface
   */
  protected $permissionCalculator;

  /**
   * The group role synchronizer service.
   *
   * @var \Drupal\group\GroupRoleSynchronizer
   */
  protected $roleSynchronizer;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    $this->permissionCalculator = $this->container->get('group_permission.chain_calculator');
    $this->roleSynchronizer = $this->container->get('group_role.synchronizer');
  }

  /**
   * Tests the calculation of the anonymous permissions.
   *
   * @covers ::calculateAnonymousPermissions
   */
  public function testCalculateAnonymousPermissions() {
    // @todo Use a proper set-up instead of the one from GroupKernelTestBase?
    $permissions = [
      'default' => [],
      'other' => [],
    ];
    $cache_tags = [
      'config:group.role.default-anonymous',
      'config:group.role.other-anonymous',
      'config:group_type_list',
      'group_permissions',
    ];
    sort($cache_tags);

    $calculated_permissions = $this->permissionCalculator->calculateAnonymousPermissions();
    $converted = $this->convertCalculatedPermissionsToArray($calculated_permissions);
    $this->assertEqualsCanonicalizing($permissions, $converted, 'Anonymous permissions are returned per group type.');
    $this->assertSame([], $calculated_permissions->getCacheContexts(), 'Anonymous permissions have the right cache contexts.');
    $this->assertSame(-1, $calculated_permissions->getCacheMaxAge(), 'Anonymous permissions have the right max cache age.');
    $this->assertEqualsCanonicalizing($cache_tags, $calculated_permissions->getCacheTags(), 'Anonymous permissions have the right cache tags.');

    $group_role = $this->entityTypeManager->getStorage('group_role')->load('default-anonymous');
    $group_role->grantPermission('view group')->save();
    $permissions['default'][] = 'view group';

    $calculated_permissions = $this->permissionCalculator->calculateAnonymousPermissions();
    $converted = $this->convertCalculatedPermissionsToArray($calculated_permissions);
    $this->assertEqualsCanonicalizing($permissions, $converted, 'Updated anonymous permissions are returned per group type.');
    $this->assertSame([], $calculated_permissions->getCacheContexts(), 'Updated anonymous permissions have the right cache contexts.');
    $this->assertSame(-1, $calculated_permissions->getCacheMaxAge(), 'Updated anonymous permissions have the right max cache age.');
    $this->assertEqualsCanonicalizing($cache_tags, $calculated_permissions->getCacheTags(), 'Updated anonymous permissions have the right cache tags.');

    $this->createGroupType(['id' => 'test']);
    $permissions['test'] = [];
    $cache_tags[] = 'config:group.role.test-anonymous';
    sort($cache_tags);

    $calculated_permissions = $this->permissionCalculator->calculateAnonymousPermissions();
    $converted = $this->convertCalculatedPermissionsToArray($calculated_permissions);
    $this->assertEqualsCanonicalizing($permissions, $converted, 'Anonymous permissions are updated after introducing a new group type.');
    $this->assertSame([], $calculated_permissions->getCacheContexts(), 'Anonymous permissions have the right cache contexts after introducing a new group type.');
    $this->assertSame(-1, $calculated_permissions->getCacheMaxAge(), 'Anonymous permissions have the right max cache age after introducing a new group type.');
    $this->assertEqualsCanonicalizing($cache_tags, $calculated_permissions->getCacheTags(), 'Anonymous permissions have the right cache tags after introducing a new group type.');
  }

  /**
   * Tests the calculation of the outsider permissions.
   *
   * @covers ::calculateOutsiderPermissions
   */
  public function testCalculateOutsiderPermissions() {
    // @todo Use a proper set-up instead of the one from GroupKernelTestBase?
    $account = $this->createUser(['roles' => ['test']]);
    $group_role_id = $this->roleSynchronizer->getGroupRoleId('default', 'test');

    $permissions = [
      'default' => ['join group', 'view group'],
      'other' => [],
    ];
    $cache_tags = [
      'config:group.role.default-outsider',
      'config:group.role.other-outsider',
      'config:group.role.' . $group_role_id,
      'config:group.role.' . $this->roleSynchronizer->getGroupRoleId('other', 'test'),
      'config:group_type_list',
      'group_permissions',
    ];
    sort($cache_tags);

    $calculated_permissions = $this->permissionCalculator->calculateOutsiderPermissions($account);
    $converted = $this->convertCalculatedPermissionsToArray($calculated_permissions);
    $this->assertEqualsCanonicalizing($permissions, $converted, 'Outsider permissions are returned per group type.');
    $this->assertSame(-1, $calculated_permissions->getCacheMaxAge(), 'Outsider permissions have the right max cache age.');
    $this->assertEqualsCanonicalizing($cache_tags, $calculated_permissions->getCacheTags(), 'Outsider permissions have the right cache tags.');

    $group_role = $this->entityTypeManager->getStorage('group_role')->load('other-outsider');
    $group_role->grantPermission('view group')->save();
    $permissions['other'][] = 'view group';

    $calculated_permissions = $this->permissionCalculator->calculateOutsiderPermissions($account);
    $converted = $this->convertCalculatedPermissionsToArray($calculated_permissions);
    $this->assertEqualsCanonicalizing($permissions, $converted, 'Updated outsider permissions are returned per group type.');
    $this->assertSame(-1, $calculated_permissions->getCacheMaxAge(), 'Updated outsider permissions have the right max cache age.');
    $this->assertEqualsCanonicalizing($cache_tags, $calculated_permissions->getCacheTags(), 'Updated outsider permissions have the right cache tags.');

    $group_role = $this->entityTypeManager->getStorage('group_role')->load($group_role_id);
    $group_role->grantPermission('edit group')->save();
    $permissions['default'][] = 'edit group';

    $calculated_permissions = $this->permissionCalculator->calculateOutsiderPermissions($account);
    $converted = $this->convertCalculatedPermissionsToArray($calculated_permissions);
    $this->assertEqualsCanonicalizing($permissions, $converted, 'Updated synchronized outsider permissions are returned per group type.');
    $this->assertSame(-1, $calculated_permissions->getCacheMaxAge(), 'Updated synchronized outsider permissions have the right max cache age.');
    $this->assertEqualsCanonicalizing($cache_tags, $calculated_permissions->getCacheTags(), 'Updated synchronized outsider permissions have the right cache tags.');

    $this->createGroupType(['id' => 'test']);
    $permissions['test'] = [];
    $cache_tags[] = 'config:group.role.test-outsider';
    $cache_tags[] = 'config:group.role.' . $this->roleSynchronizer->getGroupRoleId('test', 'test');
    sort($cache_tags);

    $calculated_permissions = $this->permissionCalculator->calculateOutsiderPermissions($account);
    $converted = $this->convertCalculatedPermissionsToArray($calculated_permissions);
    $this->assertEqualsCanonicalizing($permissions, $converted, 'Outsider permissions are updated after introducing a new group type.');
    $this->assertSame(-1, $calculated_permissions->getCacheMaxAge(), 'Outsider permissions have the right max cache age after introducing a new group type.');
    $this->assertEqualsCanonicalizing($cache_tags, $calculated_permissions->getCacheTags(), 'Outsider permissions have the right cache tags after introducing a new group type.');
  }

  /**
   * Tests the calculation of the member permissions.
   *
   * @covers ::calculateMemberPermissions
   */
  public function testCalculateMemberPermissions() {
    // @todo Use a proper set-up instead of the one from GroupKernelTestBase?
    $account = $this->createUser();
    $group = $this->createGroup(['type' => 'default']);

    $permissions = [];
    $cache_tags = ['group_permissions', 'group_content_list:plugin:group_membership:entity:' . $account->id()];
    sort($cache_tags);

    $calculated_permissions = $this->permissionCalculator->calculateMemberPermissions($account);
    $converted = $this->convertCalculatedPermissionsToArray($calculated_permissions);
    $this->assertEqualsCanonicalizing($permissions, $converted, 'Member permissions are returned per group ID.');
    $this->assertSame(-1, $calculated_permissions->getCacheMaxAge(), 'Member permissions have the right max cache age.');
    $this->assertEqualsCanonicalizing($cache_tags, $calculated_permissions->getCacheTags(), 'Member permissions have the right cache tags.');

    $group->addMember($account);
    $member = $group->getMember($account);
    $permissions[$group->id()][] = 'view group';
    $permissions[$group->id()][] = 'leave group';
    $cache_tags[] = 'config:group.role.default-member';
    $cache_tags = array_unique(array_merge($member->getCacheTags(), $cache_tags));
    sort($cache_tags);

    $calculated_permissions = $this->permissionCalculator->calculateMemberPermissions($account);
    $converted = $this->convertCalculatedPermissionsToArray($calculated_permissions);
    $this->assertEqualsCanonicalizing($permissions, $converted, 'Member permissions are returned per group ID after joining a group.');
    $this->assertSame(-1, $calculated_permissions->getCacheMaxAge(), 'Member permissions have the right max cache age after joining a group.');
    $this->assertEqualsCanonicalizing($cache_tags, $calculated_permissions->getCacheTags(), 'Member permissions have the right cache tags after joining a group.');

    // @todo This displays a desperate need for addRole() and removeRole().
    $membership = $member->getGroupContent();
    $membership->group_roles[] = 'default-custom';
    $membership->save();
    $permissions[$group->id()][] = 'join group';
    $cache_tags[] = 'config:group.role.default-custom';
    sort($cache_tags);

    $calculated_permissions = $this->permissionCalculator->calculateMemberPermissions($account);
    $converted = $this->convertCalculatedPermissionsToArray($calculated_permissions);
    $this->assertEqualsCanonicalizing($permissions, $converted, 'Updated member permissions are returned per group ID.');
    $this->assertSame(-1, $calculated_permissions->getCacheMaxAge(), 'Updated member permissions have the right max cache age.');
    $this->assertEqualsCanonicalizing($cache_tags, $calculated_permissions->getCacheTags(), 'Updated member permissions have the right cache tags.');
  }

  /**
   * Tests the calculation of the authenticated permissions.
   *
   * @covers ::calculateAuthenticatedPermissions
   * @depends testCalculateOutsiderPermissions
   * @depends testCalculateMemberPermissions
   */
  public function testCalculateAuthenticatedPermissions() {
    $account = $this->createUser();
    $group = $this->createGroup(['type' => 'default']);
    $group->addMember($account);

    $calculated_permissions = new RefinableCalculatedGroupPermissions();
    $calculated_permissions
      ->merge($this->permissionCalculator->calculateOutsiderPermissions($account))
      ->merge($this->permissionCalculator->calculateMemberPermissions($account));
    $calculated_permissions = new CalculatedGroupPermissions($calculated_permissions);

    $this->assertEquals($calculated_permissions, $this->permissionCalculator->calculateAuthenticatedPermissions($account), 'Authenticated permissions are returned as a merge of outsider and member permissions.');
  }

  /**
   * Tests the calculation of an account's permissions.
   *
   * @covers ::calculatePermissions
   * @depends testCalculateAnonymousPermissions
   * @depends testCalculateAuthenticatedPermissions
   */
  public function testCalculatePermissions() {
    $account = new AnonymousUserSession();
    $calculated_permissions = $this->permissionCalculator->calculateAnonymousPermissions();
    $this->assertEquals($calculated_permissions, $this->permissionCalculator->calculatePermissions($account), 'The calculated anonymous permissions are returned for an anonymous user.');

    $account = $this->createUser();
    $group = $this->createGroup(['type' => 'default']);
    $group->addMember($account);
    $calculated_permissions = new RefinableCalculatedGroupPermissions();
    $calculated_permissions
      ->merge($this->permissionCalculator->calculateOutsiderPermissions($account))
      ->merge($this->permissionCalculator->calculateMemberPermissions($account));
    $calculated_permissions = new CalculatedGroupPermissions($calculated_permissions);

    $this->assertEquals($calculated_permissions, $this->permissionCalculator->calculatePermissions($account), 'Calculated permissions for a member are returned as a merge of outsider and member permissions.');
  }

  /**
   * Tests whether anonymous users and 'pure' outsiders can get the same result.
   *
   * This is important for hash generation based on the calculated permissions.
   * If both audiences can get a similar result, it means they can share a hash
   * and therefore cache objects.
   *
   * @depends testCalculateAnonymousPermissions
   * @depends testCalculateAuthenticatedPermissions
   */
  public function testAnonymousAuthenticatedSameResult() {
    // @todo Use a proper set-up instead of the one from GroupKernelTestBase?
    $account = $this->createUser();

    $this->assertNotEqualsCanonicalizing(
      $this->convertCalculatedPermissionsToArray($this->permissionCalculator->calculateAnonymousPermissions()),
      $this->convertCalculatedPermissionsToArray($this->permissionCalculator->calculateAuthenticatedPermissions($account)),
      'Calculated permissions for an anonymous and outsider user with different group permissions differ.'
    );

    // Update 'default' anonymous role to have same permissions as the
    // 'default' outsider role.
    $group_role = $this->entityTypeManager->getStorage('group_role')->load('default-anonymous');
    $group_role->grantPermissions(['join group', 'view group'])->save();

    $this->assertEqualsCanonicalizing(
      $this->convertCalculatedPermissionsToArray($this->permissionCalculator->calculateAnonymousPermissions()),
      $this->convertCalculatedPermissionsToArray($this->permissionCalculator->calculateAuthenticatedPermissions($account)),
      'Calculated permissions for an anonymous and outsider user with the same group permissions are the same.'
    );
  }

  /**
   * Converts a calculated permissions object into an array.
   *
   * This is done to make comparison assertions easier. Make sure you use the
   * canonicalize option of assertEquals.
   *
   * @param \Drupal\group\Access\CalculatedGroupPermissionsInterface $calculated_permissions
   *   The calculated permissions object to convert.
   *
   * @return string[]
   *   The permissions, keyed by scope identifier.
   */
  protected function convertCalculatedPermissionsToArray(CalculatedGroupPermissionsInterface $calculated_permissions) {
    $permissions = [];
    foreach ($calculated_permissions->getItems() as $item) {
      $permissions[$item->getIdentifier()] = $item->getPermissions();
    }
    return $permissions;
  }

  /**
   * Test members permissions with multiple users, groups and group roles.
   */
  public function testCalculateMultipleMembersPermissions() {
    $account1 = $this->createUser();
    $account2 = $this->createUser();
    $group1 = $this->createGroup(['type' => 'default']);
    $group2 = $this->createGroup(['type' => 'default']);

    // Expected values.
    $permissions1 = [];
    $permissions2 = [];

    // Assert permissions for accounts when users are not group members.
    $calculated_permissions1 = $this->permissionCalculator->calculateMemberPermissions($account1);
    $converted1 = $this->convertCalculatedPermissionsToArray($calculated_permissions1);
    $this->assertEqualsCanonicalizing($permissions1, $converted1, 'Member permissions are returned per group ID.');
    $calculated_permissions2 = $this->permissionCalculator->calculateMemberPermissions($account2);
    $converted2 = $this->convertCalculatedPermissionsToArray($calculated_permissions2);
    $this->assertEqualsCanonicalizing($permissions2, $converted2, 'Member permissions are returned per group ID.');

    // Add account1 to group1.
    $group1->addMember($account1);
    $permissions1[$group1->id()][] = 'view group';
    $permissions1[$group1->id()][] = 'leave group';

    $calculated_permissions1 = $this->permissionCalculator->calculateMemberPermissions($account1);
    $converted1 = $this->convertCalculatedPermissionsToArray($calculated_permissions1);
    $this->assertEqualsCanonicalizing($permissions1, $converted1, 'Member permissions are returned per group ID after joining a group.');

    // Add account2 to group2 and apply an additional role.
    $group2->addMember($account2);
    $member2 = $group2->getMember($account2);
    $membership2 = $member2->getGroupContent();
    $membership2->group_roles[] = 'default-custom';
    $membership2->save();
    $permissions2[$group2->id()][] = 'view group';
    $permissions2[$group2->id()][] = 'leave group';
    $permissions2[$group2->id()][] = 'join group';

    // Permissions for account1 did not change.
    $calculated_permissions1 = $this->permissionCalculator->calculateMemberPermissions($account1);
    $converted1 = $this->convertCalculatedPermissionsToArray($calculated_permissions1);
    $this->assertEqualsCanonicalizing($permissions1, $converted1, 'Member permissions are returned per group ID after joining a group.');
    // Assert permissions for account2.
    $calculated_permissions2 = $this->permissionCalculator->calculateMemberPermissions($account2);
    $converted2 = $this->convertCalculatedPermissionsToArray($calculated_permissions2);
    $this->assertEqualsCanonicalizing($permissions2, $converted2, 'Updated member permissions are returned per group ID.');

    // Remove "default-custom" group role for account2.
    $membership2->group_roles = ['default-member'];
    $membership2->save();
    $permissions2 = [
      $group2->id() => [
        'view group',
        'leave group',
      ],
    ];

    // Assert permissions have changed for account2.
    $calculated_permissions2 = $this->permissionCalculator->calculateMemberPermissions($account2);
    $converted2 = $this->convertCalculatedPermissionsToArray($calculated_permissions2);
    $this->assertEqualsCanonicalizing($permissions2, $converted2, 'Updated member permissions are returned per group ID.');

    // Remove account2 from group2.
    $group2->removeMember($account2);

    $permissions2 = [];
    $calculated_permissions2 = $this->permissionCalculator->calculateMemberPermissions($account2);
    $converted2 = $this->convertCalculatedPermissionsToArray($calculated_permissions2);
    $this->assertEqualsCanonicalizing($permissions2, $converted2, 'Updated member permissions are returned per group ID.');
  }

}
