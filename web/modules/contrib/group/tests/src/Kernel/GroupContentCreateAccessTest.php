<?php

namespace Drupal\Tests\group\Kernel;

use Drupal\Core\Url;
use Drupal\group\Entity\GroupInterface;
use Symfony\Cmf\Component\Routing\RouteObjectInterface;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Request;

/**
 * Tests the group content create access for groups.
 *
 * @group group
 */
class GroupContentCreateAccessTest extends GroupKernelTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = ['group_test_plugin', 'node'];

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

    $this->installSchema('node', ['node_access']);
    $this->installEntitySchema('node');
    $this->installEntitySchema('entity_test_with_owner');
    $this->createNodeType(['type' => 'page']);

    $this->accessManager = $this->container->get('access_manager');
    $this->routeProvider = $this->container->get('router.route_provider');
    $this->groupType = $this->createGroupType([
      'id' => 'create_access_test',
      'creator_membership' => FALSE,
    ]);

    // Enable the test plugins on the group type.
    /** @var \Drupal\group\Entity\Storage\GroupContentTypeStorageInterface $storage */
    $storage = $this->entityTypeManager->getStorage('group_content_type');
    $storage->save($storage->createFromPlugin($this->groupType, 'entity_test_as_content'));
    $storage->save($storage->createFromPlugin($this->groupType, 'node_as_content:page'));

    $this->adminRole = $this->entityTypeManager->getStorage('group_role')->create([
      'id' => 'create_access_test-admin',
      'label' => 'Create test admin',
      'weight' => 0,
      'group_type' => $this->groupType->id(),
    ]);
    $this->adminRole->grantPermission('administer group')->save();
  }

  /**
   * Tests access to the create/add overview page.
   *
   * @dataProvider pageAccessProvider
   */
  public function testPageAccess($route, $outsider_permissions, $member_permissions, $outsider_access, $member_access, $admin_access, $message) {
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

    $request = $this->createRequest($route, $group);
    $this->assertSame($outsider_access, $this->accessManager->checkRequest($request, $outsider), $message);
    $this->assertSame($member_access, $this->accessManager->checkRequest($request, $member), $message);
    $this->assertSame($admin_access, $this->accessManager->checkRequest($request, $admin), $message);
  }

  /**
   * Data provider for testPageAccess().
   *
   * @return array
   *   A list of testPageAccess method arguments.
   */
  public function pageAccessProvider() {
    $cases = [];

    $cases['create-page-access-one'] = [
      'entity.group_content.create_page',
      [],
      ['create entity_test_as_content entity'],
      FALSE,
      TRUE,
      TRUE,
      'Testing the _group_content_create_any_entity_access route access check with create access from one plugin',
    ];

    $cases['create-page-access-multi'] = [
      'entity.group_content.create_page',
      [],
      ['create entity_test_as_content entity', 'create node_as_content:page entity'],
      FALSE,
      TRUE,
      TRUE,
      'Testing the _group_content_create_any_entity_access route access check with create access from multiple plugins',
    ];

    $cases['create-page-with-add-access'] = [
      'entity.group_content.create_page',
      [],
      ['create entity_test_as_content content'],
      FALSE,
      FALSE,
      TRUE,
      'Testing the _group_content_create_any_entity_access route access check with add access from one plugin',
    ];

    $cases['add-page-access-one'] = [
      'entity.group_content.add_page',
      [],
      ['create entity_test_as_content content'],
      FALSE,
      TRUE,
      TRUE,
      'Testing the _group_content_create_any_access route access check with add access from one plugin',
    ];

    $cases['add-page-access-multi'] = [
      'entity.group_content.add_page',
      [],
      ['create entity_test_as_content content', 'create node_as_content:page content'],
      FALSE,
      TRUE,
      TRUE,
      'Testing the _group_content_create_any_access route access check with add access from multiple plugins',
    ];

    $cases['add-page-with-create-access'] = [
      'entity.group_content.add_page',
      [],
      ['create entity_test_as_content entity'],
      FALSE,
      FALSE,
      TRUE,
      'Testing the _group_content_create_any_access route access check with create access from one plugin',
    ];

    return $cases;
  }

  /**
   * Tests access to the create/add form.
   *
   * @dataProvider formAccessProvider
   */
  public function testFormAccess($route, $outsider_permissions, $member_permissions, $outsider_access, $member_access, $admin_access, $message) {
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

    $request = $this->createRequest($route, $group, 'entity_test_as_content');
    $this->assertSame($outsider_access, $this->accessManager->checkRequest($request, $outsider), $message);
    $this->assertSame($member_access, $this->accessManager->checkRequest($request, $member), $message);
    $this->assertSame($admin_access, $this->accessManager->checkRequest($request, $admin), $message);
  }

  /**
   * Data provider for testFormAccess().
   *
   * @return array
   *   A list of testFormAccess method arguments.
   */
  public function formAccessProvider() {
    $cases = [];

    $cases['create-form-access'] = [
      'entity.group_content.create_form',
      [],
      ['create entity_test_as_content entity'],
      FALSE,
      TRUE,
      TRUE,
      'Testing the _group_content_create_entity_access route access check with create access',
    ];

    $cases['create-form-access-wrong-plugin'] = [
      'entity.group_content.create_form',
      [],
      ['create node_as_content:page entity'],
      FALSE,
      FALSE,
      TRUE,
      'Testing the _group_content_create_entity_access route access check with create access from the wrong plugin',
    ];

    $cases['create-form-with-add-access'] = [
      'entity.group_content.create_form',
      [],
      ['create entity_test_as_content content'],
      FALSE,
      FALSE,
      TRUE,
      'Testing the _group_content_create_entity_access route access check with add access',
    ];

    $cases['add-form-access'] = [
      'entity.group_content.add_form',
      [],
      ['create entity_test_as_content content'],
      FALSE,
      TRUE,
      TRUE,
      'Testing the _group_content_create_access route access check with add access',
    ];

    $cases['add-form-access-wrong-plugin'] = [
      'entity.group_content.add_form',
      [],
      ['create node_as_content:page content'],
      FALSE,
      FALSE,
      TRUE,
      'Testing the _group_content_create_access route access check with add access from the wrong plugin',
    ];

    $cases['add-form-with-create-access'] = [
      'entity.group_content.add_form',
      [],
      ['create entity_test_as_content entity'],
      FALSE,
      FALSE,
      TRUE,
      'Testing the _group_content_create_access route access check with create access',
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
   * @param string|null $plugin_id
   *   (optional) The plugin ID.
   *
   * @return \Symfony\Component\HttpFoundation\Request
   *   The request.
   */
  protected function createRequest($route_name, GroupInterface $group, $plugin_id = NULL) {
    $params = ['group' => $group->id()];
    $attributes = ['group' => $group];

    if ($plugin_id) {
      $params['plugin_id'] = $plugin_id;
      $attributes['plugin_id'] = $plugin_id;
    }

    $attributes[RouteObjectInterface::ROUTE_NAME] = $route_name;
    $attributes[RouteObjectInterface::ROUTE_OBJECT] = $this->routeProvider->getRouteByName($route_name);
    $attributes['_raw_variables'] = new ParameterBag($params);

    $request = Request::create(Url::fromRoute($route_name, $params)->toString());
    $request->attributes->add($attributes);

    // Push the request to the request stack so `current_route_match` works.
    $this->container->get('request_stack')->push($request);
    return $request;
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
