<?php

namespace Drupal\Tests\group\Unit;

use Drupal\Core\Entity\EntityPublishedInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\group\Plugin\GroupContentPermissionProvider;
use Drupal\Tests\UnitTestCase;
use Drupal\user\EntityOwnerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Tests the default GroupContentEnabler permission_provider handler.
 *
 * @coversDefaultClass \Drupal\group\Plugin\GroupContentPermissionProvider
 * @group group
 */
class GroupContentPermissionProviderTest extends UnitTestCase {

  /**
   * Tests the admin permission name.
   *
   * @param mixed $expected
   *   The expected return value.
   * @param string $plugin_id
   *   The plugin ID.
   * @param array $definition
   *   The plugin definition.
   * @param bool $implements_owner
   *   Whether the plugin's entity type deals with ownership.
   * @param bool $implements_published
   *   Whether the plugin's entity type deals with publishing of entities.
   *
   * @covers ::getAdminPermission
   * @dataProvider adminPermissionProvider
   */
  public function testGetAdminPermission($expected, $plugin_id, array $definition, $implements_owner, $implements_published) {
    $permission_provider = $this->createPermissionProvider($plugin_id, $definition, $implements_owner, $implements_published);
    $this->assertEquals($expected, $permission_provider->getAdminPermission());
  }

  /**
   * Data provider for testGetAdminPermission().
   *
   * @return array
   *   A list of testGetAdminPermission method arguments.
   */
  public function adminPermissionProvider() {
    $cases = [];
    foreach ($this->getPermissionProviderScenarios() as $scenario) {
        $case = $scenario;
        $case['expected'] = $case['definition']['admin_permission'];
        $cases[] = $case;
    }
    return $cases;
  }

  /**
   * Tests the relation view permission name.
   *
   * @param mixed $expected
   *   The expected return value.
   * @param string $plugin_id
   *   The plugin ID.
   * @param array $definition
   *   The plugin definition.
   * @param bool $implements_owner
   *   Whether the plugin's entity type deals with ownership.
   * @param bool $implements_published
   *   Whether the plugin's entity type deals with publishing of entities.
   * @param string $scope
   *   The $scope parameter for the tested method.
   *
   * @covers ::getRelationViewPermission
   * @dataProvider relationViewPermissionProvider
   */
  public function testGetRelationViewPermission($expected, $plugin_id, array $definition, $implements_owner, $implements_published, $scope) {
    $permission_provider = $this->createPermissionProvider($plugin_id, $definition, $implements_owner, $implements_published);
    $this->assertEquals($expected, $permission_provider->getRelationViewPermission($scope));
  }

  /**
   * Data provider for testGetRelationViewPermission().
   *
   * @return array
   *   A list of testGetRelationViewPermission method arguments.
   */
  public function relationViewPermissionProvider() {
    $cases = [];
    foreach ($this->getPermissionProviderScenarios() as $scenario) {
      foreach (['any', 'own'] as $scope) {
        $case = $scenario;
        $case['scope'] = $scope;

        // View own relation is not present in version 1.x.
        $case['expected'] = $scope === 'any'
          ? "view {$scenario['plugin_id']} content"
          : FALSE;

        $cases[] = $case;
      }
    }
    return $cases;
  }

  /**
   * Tests the relation update permission name.
   *
   * @param mixed $expected
   *   The expected return value.
   * @param string $plugin_id
   *   The plugin ID.
   * @param array $definition
   *   The plugin definition.
   * @param bool $implements_owner
   *   Whether the plugin's entity type deals with ownership.
   * @param bool $implements_published
   *   Whether the plugin's entity type deals with publishing of entities.
   * @param string $scope
   *   The $scope parameter for the tested method.
   *
   * @covers ::getRelationUpdatePermission
   * @dataProvider relationUpdatePermissionProvider
   */
  public function testGetRelationUpdatePermission($expected, $plugin_id, array $definition, $implements_owner, $implements_published, $scope) {
    $permission_provider = $this->createPermissionProvider($plugin_id, $definition, $implements_owner, $implements_published);
    $this->assertEquals($expected, $permission_provider->getRelationUpdatePermission($scope));
  }

  /**
   * Data provider for testGetRelationUpdatePermission().
   *
   * @return array
   *   A list of testGetRelationUpdatePermission method arguments.
   */
  public function relationUpdatePermissionProvider() {
    $cases = [];
    foreach ($this->getPermissionProviderScenarios() as $scenario) {
      foreach (['any', 'own'] as $scope) {
        $case = $scenario;
        $case['scope'] = $scope;
        $case['expected'] = "update $scope {$scenario['plugin_id']} content";
        $cases[] = $case;
      }
    }
    return $cases;
  }

  /**
   * Tests the relation delete permission name.
   *
   * @param mixed $expected
   *   The expected return value.
   * @param string $plugin_id
   *   The plugin ID.
   * @param array $definition
   *   The plugin definition.
   * @param bool $implements_owner
   *   Whether the plugin's entity type deals with ownership.
   * @param bool $implements_published
   *   Whether the plugin's entity type deals with publishing of entities.
   * @param string $scope
   *   The $scope parameter for the tested method.
   *
   * @covers ::getRelationDeletePermission
   * @dataProvider relationDeletePermissionProvider
   */
  public function testGetRelationDeletePermission($expected, $plugin_id, array $definition, $implements_owner, $implements_published, $scope) {
    $permission_provider = $this->createPermissionProvider($plugin_id, $definition, $implements_owner, $implements_published);
    $this->assertEquals($expected, $permission_provider->getRelationDeletePermission($scope));
  }

  /**
   * Data provider for testGetRelationDeletePermission().
   *
   * @return array
   *   A list of testGetRelationDeletePermission method arguments.
   */
  public function relationDeletePermissionProvider() {
    $cases = [];
    foreach ($this->getPermissionProviderScenarios() as $scenario) {
      foreach (['any', 'own'] as $scope) {
        $case = $scenario;
        $case['scope'] = $scope;
        $case['expected'] = "delete $scope {$scenario['plugin_id']} content";
        $cases[] = $case;
      }
    }
    return $cases;
  }

  /**
   * Tests the relation create permission name.
   *
   * @param mixed $expected
   *   The expected return value.
   * @param string $plugin_id
   *   The plugin ID.
   * @param array $definition
   *   The plugin definition.
   * @param bool $implements_owner
   *   Whether the plugin's entity type deals with ownership.
   * @param bool $implements_published
   *   Whether the plugin's entity type deals with publishing of entities.
   *
   * @covers ::getRelationCreatePermission
   * @dataProvider relationCreatePermissionProvider
   */
  public function testGetRelationCreatePermission($expected, $plugin_id, array $definition, $implements_owner, $implements_published) {
    $permission_provider = $this->createPermissionProvider($plugin_id, $definition, $implements_owner, $implements_published);
    $this->assertEquals($expected, $permission_provider->getRelationCreatePermission());
  }

  /**
   * Data provider for testGetRelationCreatePermission().
   *
   * @return array
   *   A list of testGetRelationCreatePermission method arguments.
   */
  public function relationCreatePermissionProvider() {
    $cases = [];
    foreach ($this->getPermissionProviderScenarios() as $scenario) {
      $case = $scenario;
      $case['expected'] = "create {$scenario['plugin_id']} content";
      $cases[] = $case;
    }
    return $cases;
  }

  /**
   * Tests the entity view permission name.
   *
   * @param mixed $expected
   *   The expected return value.
   * @param string $plugin_id
   *   The plugin ID.
   * @param array $definition
   *   The plugin definition.
   * @param bool $implements_owner
   *   Whether the plugin's entity type deals with ownership.
   * @param bool $implements_published
   *   Whether the plugin's entity type deals with publishing of entities.
   * @param string $scope
   *   The $scope parameter for the tested method.
   *
   * @covers ::getEntityViewPermission
   * @dataProvider entityViewPermissionProvider
   */
  public function testGetEntityViewPermission($expected, $plugin_id, array $definition, $implements_owner, $implements_published, $scope) {
    $permission_provider = $this->createPermissionProvider($plugin_id, $definition, $implements_owner, $implements_published);
    $this->assertEquals($expected, $permission_provider->getEntityViewPermission($scope));
  }

  /**
   * Data provider for testGetEntityViewPermission().
   *
   * @return array
   *   A list of testGetEntityViewPermission method arguments.
   */
  public function entityViewPermissionProvider() {
    $cases = [];
    foreach ($this->getPermissionProviderScenarios() as $scenario) {
      foreach (['any', 'own'] as $scope) {
        $case = $scenario;
        $case['scope'] = $scope;
        $case['expected'] = FALSE;
        if ($case['definition']['entity_access']) {
          // View own entity is not present in version 1.x.
          if ($scope === 'any') {
            $case['expected'] = "view {$scenario['plugin_id']} entity";
          }
        }
        $cases[] = $case;
      }
    }
    return $cases;
  }

  /**
   * Tests the entity view unpublished permission name.
   *
   * @param mixed $expected
   *   The expected return value.
   * @param string $plugin_id
   *   The plugin ID.
   * @param array $definition
   *   The plugin definition.
   * @param bool $implements_owner
   *   Whether the plugin's entity type deals with ownership.
   * @param bool $implements_published
   *   Whether the plugin's entity type deals with publishing of entities.
   * @param string $scope
   *   The $scope parameter for the tested method.
   *
   * @covers ::getEntityViewUnpublishedPermission
   * @dataProvider entityViewUnpublishedPermissionProvider
   */
  public function testGetEntityViewUnpublishedPermission($expected, $plugin_id, array $definition, $implements_owner, $implements_published, $scope) {
    $permission_provider = $this->createPermissionProvider($plugin_id, $definition, $implements_owner, $implements_published);
    $this->assertEquals($expected, $permission_provider->getEntityViewUnpublishedPermission($scope));
  }

  /**
   * Data provider for testGetEntityViewUnpublishedPermission().
   *
   * @return array
   *   A list of testGetEntityViewUnpublishedPermission method arguments.
   */
  public function entityViewUnpublishedPermissionProvider() {
    $cases = [];
    foreach ($this->getPermissionProviderScenarios() as $scenario) {
      foreach (['any', 'own'] as $scope) {
        $case = $scenario;
        $case['scope'] = $scope;
        $case['expected'] = FALSE;
        if ($case['definition']['entity_access'] && $case['implements_published']) {
          // View own unpublished entity is not implemented yet.
          if ($scope === 'any') {
            $case['expected'] = "view $scope unpublished {$scenario['plugin_id']} entity";
          }
        }
        $cases[] = $case;
      }
    }
    return $cases;
  }

  /**
   * Tests the entity update permission name.
   *
   * @param mixed $expected
   *   The expected return value.
   * @param string $plugin_id
   *   The plugin ID.
   * @param array $definition
   *   The plugin definition.
   * @param bool $implements_owner
   *   Whether the plugin's entity type deals with ownership.
   * @param bool $implements_published
   *   Whether the plugin's entity type deals with publishing of entities.
   * @param string $scope
   *   The $scope parameter for the tested method.
   *
   * @covers ::getEntityUpdatePermission
   * @dataProvider entityUpdatePermissionProvider
   */
  public function testGetEntityUpdatePermission($expected, $plugin_id, array $definition, $implements_owner, $implements_published, $scope) {
    $permission_provider = $this->createPermissionProvider($plugin_id, $definition, $implements_owner, $implements_published);
    $this->assertEquals($expected, $permission_provider->getEntityUpdatePermission($scope));
  }

  /**
   * Data provider for testGetEntityUpdatePermission().
   *
   * @return array
   *   A list of testGetEntityUpdatePermission method arguments.
   */
  public function entityUpdatePermissionProvider() {
    $cases = [];
    foreach ($this->getPermissionProviderScenarios() as $scenario) {
      foreach (['any', 'own'] as $scope) {
        $case = $scenario;
        $case['scope'] = $scope;
        $case['expected'] = FALSE;
        if ($case['definition']['entity_access']) {
          if ($case['implements_owner'] || $scope === 'any') {
            $case['expected'] = "update $scope {$scenario['plugin_id']} entity";
          }
        }
        $cases[] = $case;
      }
    }
    return $cases;
  }

  /**
   * Tests the entity delete permission name.
   *
   * @param mixed $expected
   *   The expected return value.
   * @param string $plugin_id
   *   The plugin ID.
   * @param array $definition
   *   The plugin definition.
   * @param bool $implements_owner
   *   Whether the plugin's entity type deals with ownership.
   * @param bool $implements_published
   *   Whether the plugin's entity type deals with publishing of entities.
   * @param string $scope
   *   The $scope parameter for the tested method.
   *
   * @covers ::getEntityDeletePermission
   * @dataProvider entityDeletePermissionProvider
   */
  public function testGetEntityDeletePermission($expected, $plugin_id, array $definition, $implements_owner, $implements_published, $scope) {
    $permission_provider = $this->createPermissionProvider($plugin_id, $definition, $implements_owner, $implements_published);
    $this->assertEquals($expected, $permission_provider->getEntityDeletePermission($scope));
  }

  /**
   * Data provider for testGetEntityDeletePermission().
   *
   * @return array
   *   A list of testGetEntityDeletePermission method arguments.
   */
  public function entityDeletePermissionProvider() {
    $cases = [];
    foreach ($this->getPermissionProviderScenarios() as $scenario) {
      foreach (['any', 'own'] as $scope) {
        $case = $scenario;
        $case['scope'] = $scope;
        $case['expected'] = FALSE;
        if ($case['definition']['entity_access']) {
          if ($case['implements_owner'] || $scope === 'any') {
            $case['expected'] = "delete $scope {$scenario['plugin_id']} entity";
          }
        }
        $cases[] = $case;
      }
    }
    return $cases;
  }

  /**
   * Tests the entity create permission name.
   *
   * @param mixed $expected
   *   The expected return value.
   * @param string $plugin_id
   *   The plugin ID.
   * @param array $definition
   *   The plugin definition.
   * @param bool $implements_owner
   *   Whether the plugin's entity type deals with ownership.
   * @param bool $implements_published
   *   Whether the plugin's entity type deals with publishing of entities.
   *
   * @covers ::getEntityCreatePermission
   * @dataProvider entityCreatePermissionProvider
   */
  public function testGetEntityCreatePermission($expected, $plugin_id, array $definition, $implements_owner, $implements_published) {
    $permission_provider = $this->createPermissionProvider($plugin_id, $definition, $implements_owner, $implements_published);
    $this->assertEquals($expected, $permission_provider->getEntityCreatePermission());
  }

  /**
   * Data provider for testGetEntityCreatePermission().
   *
   * @return array
   *   A list of testGetEntityCreatePermission method arguments.
   */
  public function entityCreatePermissionProvider() {
    $cases = [];
    foreach ($this->getPermissionProviderScenarios() as $scenario) {
      $case = $scenario;
      $case['expected'] = FALSE;
      if ($case['definition']['entity_access']) {
        $case['expected'] = "create {$scenario['plugin_id']} entity";
      }
      $cases[] = $case;
    }
    return $cases;
  }

  /**
   * Tests the permission name getter.
   *
   * @param string $plugin_id
   *   The plugin ID.
   * @param array $definition
   *   The plugin definition.
   * @param bool $implements_owner
   *   Whether the plugin's entity type deals with ownership.
   * @param bool $implements_published
   *   Whether the plugin's entity type deals with publishing of entities.
   * @param string $operation
   *   The $operation parameter for the tested method.
   * @param string $target
   *   The $target parameter for the tested method.
   * @param string $scope
   *   The $scope parameter for the tested method.
   *
   * @covers ::getPermission
   * @dataProvider getPermissionProvider
   */
  public function testGetPermission($plugin_id, array $definition, $implements_owner, $implements_published, $operation, $target, $scope) {
    $permission_provider = $this->createPermissionProvider($plugin_id, $definition, $implements_owner, $implements_published);
    $expected = FALSE;

    if ($target === 'relation') {
      switch ($operation) {
        case 'view':
          $expected = $permission_provider->getRelationViewPermission($scope);
          break;

        case 'update':
          $expected = $permission_provider->getRelationUpdatePermission($scope);
          break;

        case 'delete':
          $expected = $permission_provider->getRelationDeletePermission($scope);
          break;

        case 'create':
          $expected = $permission_provider->getRelationCreatePermission();
          break;
      }
    }
    elseif ($target === 'entity') {
      switch ($operation) {
        case 'view':
          $expected = $permission_provider->getEntityViewPermission($scope);
          break;

        case 'view unpublished':
          $expected = $permission_provider->getEntityViewUnpublishedPermission($scope);
          break;

        case 'update':
          $expected = $permission_provider->getEntityUpdatePermission($scope);
          break;

        case 'delete':
          $expected = $permission_provider->getEntityDeletePermission($scope);
          break;

        case 'create':
          $expected = $permission_provider->getEntityCreatePermission();
          break;
      }
    }
    
    $this->assertEquals($expected, $permission_provider->getPermission($operation, $target, $scope));
  }

  /**
   * Data provider for testGetPermission().
   *
   * @return array
   *   A list of testGetPermission method arguments.
   */
  public function getPermissionProvider() {
    $cases = [];
    foreach ($this->getPermissionProviderScenarios() as $scenario) {
      foreach (['view', 'view unpublished', 'update', 'delete', 'create'] as $operation) {
        foreach (['relation', 'entity'] as $target) {
          foreach (['any', 'own'] as $scope) {
            $case = $scenario;
            $case['operation'] = $operation;
            $case['target'] = $target;
            $case['scope'] = $scope;
            unset($case['expected']);
            $cases[] = $case;
          }
        }
      }
    }
    return $cases;
  }

  /**
   * Tests the permission builder.
   *
   * @param string $plugin_id
   *   The plugin ID.
   * @param array $definition
   *   The plugin definition.
   * @param bool $implements_owner
   *   Whether the plugin's entity type deals with ownership.
   * @param bool $implements_published
   *   Whether the plugin's entity type deals with publishing of entities.
   *
   * @covers ::buildPermissions
   * @dataProvider buildPermissionsProvider
   */
  public function testBuildPermissions($plugin_id, array $definition, $implements_owner, $implements_published) {
    $permission_provider = $this->createPermissionProvider($plugin_id, $definition, $implements_owner, $implements_published);
    $permissions = $permission_provider->buildPermissions();

    // Test the admin permission being restricted.
    if (!empty($definition['admin_permission'])) {
      $admin_permission = $permission_provider->getAdminPermission();
      $this->assertArrayHasKey($admin_permission, $permissions);
      $this->assertArrayHasKey('restrict access', $permissions[$admin_permission]);
      $this->assertTrue($permissions[$admin_permission]['restrict access']);
    }

    // We do not test all permissions here as they are thoroughly covered in
    // their dedicated getter test. Simply test that the labels of common
    // permissions are prefixed properly.
    if ($permission = $permission_provider->getRelationViewPermission()) {
      $this->assertArrayHasKey($permission, $permissions);
      $this->assertStringStartsWith('Relation: ', $permissions[$permission]['title']);
    }
    if ($permission = $permission_provider->getEntityViewPermission()) {
      $this->assertArrayHasKey($permission, $permissions);
      $this->assertStringStartsWith('Entity: ', $permissions[$permission]['title']);
    }
  }

  /**
   * Data provider for testBuildPermissions().
   *
   * @return array
   *   A list of testBuildPermissions method arguments.
   */
  public function buildPermissionsProvider() {
    $cases = $this->getPermissionProviderScenarios();
    foreach ($cases as &$case) {
      unset($case['expected']);
    }
    return $cases;
  }

  /**
   * All possible scenarios for a permission provider.
   *
   * @return array
   *   A set of test cases to be used in data providers.
   */
  protected function getPermissionProviderScenarios() {
    $scenarios = [];

    foreach ([TRUE, FALSE] as $implements_owner) {
      foreach ([TRUE, FALSE] as $implements_published) {
        foreach ([TRUE, FALSE] as $entity_access) {
          foreach (['administer foo', FALSE] as $admin_permission) {
            $scenarios[] = [
              'expected' => NULL,
              // We use a derivative ID to prove these work.
              'plugin_id' => 'foo:baz',
              'definition' => [
                'id' => 'foo',
                'label' => 'Foo',
                'entity_type_id' => 'bar',
                'entity_access' => $entity_access,
                'admin_permission' => $admin_permission,
              ],
              'implements_owner' => $implements_owner,
              'implements_published' => $implements_published,
            ];
          }
        }
      }
    }

    return $scenarios;
  }

  /**
   * Instantiates a default permission provider handler.
   *
   * @return \Drupal\group\Plugin\GroupContentPermissionProvider
   *   The default permission provider handler.
   */
  protected function createPermissionProvider($plugin_id, $definition, $implements_owner, $implements_published) {
    $this->assertNotEmpty($definition['entity_type_id']);

    $entity_type = $this->prophesize(EntityTypeInterface::class);
    $entity_type->entityClassImplements(EntityOwnerInterface::class)->willReturn($implements_owner);
    $entity_type->entityClassImplements(EntityPublishedInterface::class)->willReturn($implements_published);
    $entity_type->getSingularLabel()->willReturn('Bar');

    $entity_type_manager = $this->prophesize(EntityTypeManagerInterface::class);
    $entity_type_manager->getDefinition($definition['entity_type_id'])->willReturn($entity_type->reveal());

    $container = $this->prophesize(ContainerInterface::class);
    $container->get('entity_type.manager')->willReturn($entity_type_manager->reveal());

    return GroupContentPermissionProvider::createInstance($container->reveal(), $plugin_id, $definition);
  }

}
