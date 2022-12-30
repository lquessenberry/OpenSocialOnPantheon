<?php

namespace Drupal\Tests\group\Kernel;

use Drupal\Core\Site\Settings;

/**
 * Tests whether group roles are actually synchronized.
 *
 * @group group
 */
class GroupRoleSynchronizationTest extends GroupKernelTestBase {

  /**
   * The group role synchronizer service.
   *
   * @var \Drupal\group\GroupRoleSynchronizer
   */
  protected $groupRoleSynchronizer;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    // The sheer functionality of the synchronizer service is tested elsewhere
    // in \Drupal\Tests\group\Unit\GroupRoleSynchronizerTest, so we can rely on
    // it for the sake of this test.
    $this->groupRoleSynchronizer = $this->container->get('group_role.synchronizer');
  }

  /**
   * Creates a user role.
   *
   * @param string $id
   *   The ID of the user role to create. Label will be set to the ID with the
   *   first letter converted to upper case.
   *
   * @return \Drupal\user\RoleInterface
   *   The created user role.
   */
  protected function createUserRole($id) {
    $role = $this->entityTypeManager->getStorage('user_role')
      ->create([
        'id' => $id,
        'label' => ucfirst($id),
      ]);
    $role->save();
    return $role;
  }

  /**
   * Tests whether a new user role syncs to group roles.
   *
   * @covers \Drupal\group\Entity\Storage\GroupRoleStorage::createSynchronized
   * @uses group_user_role_insert
   */
  public function testUserRoleCreation() {
    $role = $this->createUserRole('editor');
    $defaultGroupRoleId = $this->groupRoleSynchronizer->getGroupRoleId('default', $role->id());
    $otherGroupRoleId = $this->groupRoleSynchronizer->getGroupRoleId('other', $role->id());
    $group_roles = $this->entityTypeManager->getStorage('group_role')->loadMultiple();

    $this->assertArrayHasKey($defaultGroupRoleId, $group_roles, 'Synchronized role found for default group type');
    $this->assertArrayHasKey($otherGroupRoleId, $group_roles, 'Synchronized role found for other group type');
    $this->assertEquals($role->label(), $group_roles[$otherGroupRoleId]->label(), 'Synchronized roles share the same label');
    $this->assertEquals($role->getWeight(), $group_roles[$otherGroupRoleId]->getWeight(), 'Synchronized roles share the same weight');
  }

  /**
   * Tests whether an updated user role syncs to group roles.
   *
   * @covers \Drupal\group\Entity\Storage\GroupRoleStorage::updateSynchronizedLabels
   * @depends testUserRoleCreation
   * @uses group_user_role_update
   */
  public function testUserRoleUpdate() {
    $role = $this->createUserRole('editor');
    $role->set('label', 'Super-editor');
    $role->save();

    $defaultGroupRoleId = $this->groupRoleSynchronizer->getGroupRoleId('default', $role->id());
    $group_role = $this->entityTypeManager->getStorage('group_role')->load($defaultGroupRoleId);
    $this->assertEquals('Super-editor', $group_role->label(), 'Updated synchronized roles share the same label');
  }

  /**
   * Tests whether a deleted user role syncs to group roles.
   *
   * @coversNothing
   */
  public function testUserRoleDelete() {
    $role = $this->createUserRole('editor');

    $role->delete();
    $defaultGroupRoleId = $this->groupRoleSynchronizer->getGroupRoleId('default', 'editor');
    $group_roles = $this->entityTypeManager->getStorage('group_role')->loadMultiple();

    $this->assertArrayNotHasKey($defaultGroupRoleId, $group_roles, 'Synchronized role not found for deleted global role');
  }

  /**
   * Tests whether a new group type receives synchronized group roles.
   *
   * @covers \Drupal\group\Entity\Storage\GroupRoleStorage::createSynchronized
   * @uses \Drupal\group\Entity\GroupType::postSave
   */
  public function testGroupTypeCreation() {
    $role = $this->createUserRole('editor');

    $group_type = $this->entityTypeManager->getStorage('group_type')->create([
      'id' => $this->randomMachineName(),
      'label' => $this->randomString(),
    ]);
    $group_type->save();
    $group_roles = $this->entityTypeManager->getStorage('group_role')->loadMultiple();
    $group_role_id = $this->groupRoleSynchronizer->getGroupRoleId($group_type->id(), $role->id());

    $this->assertArrayHasKey($group_role_id, $group_roles, 'Synchronized role found for new group type');
  }

  /**
   * Tests whether a module with user roles receives synchronized group roles.
   *
   * @covers \Drupal\group\Entity\Storage\GroupRoleStorage::createSynchronized
   * @uses group_modules_installed
   */
  public function testModuleInstalled() {
    $defaultGroupRoleId = $this->groupRoleSynchronizer->getGroupRoleId('default', 'test');
    $otherGroupRoleId = $this->groupRoleSynchronizer->getGroupRoleId('other', 'test');
    $group_roles = $this->entityTypeManager->getStorage('group_role')->loadMultiple();

    $this->assertArrayHasKey($defaultGroupRoleId, $group_roles, 'Synchronized role found for default group type');
    $this->assertArrayHasKey($otherGroupRoleId, $group_roles, 'Synchronized role found for other group type');
    $this->assertEquals('Test', $group_roles[$otherGroupRoleId]->label(), 'Synchronized roles share the same label');
    $this->assertEquals(99, $group_roles[$otherGroupRoleId]->getWeight(), 'Synchronized roles share the same weight');
  }

  /**
   * Tests whether an imported group type receives synchronized group roles.
   *
   * @covers \Drupal\group\Entity\Storage\GroupRoleStorage::createSynchronized
   * @uses \Drupal\group\EventSubscriber\ConfigSubscriber::onConfigImport
   */
  public function testConfigImport() {
    $role = $this->createUserRole('editor');

    // The system.site key is required for import validation.
    // See: https://www.drupal.org/project/drupal/issues/2995062
    $this->installConfig(['system']);

    // Simulate config data to import.
    $active = $this->container->get('config.storage');
    $sync = $this->container->get('config.storage.sync');
    $this->copyConfig($active, $sync);

    // Manually add the 'import' group type to the synchronization directory.
    $test_dir = __DIR__ . '/../../modules/group_test_config/sync';
    $sync_dir = Settings::get('config_sync_directory');
    $file_system = $this->container->get('file_system');

    $file_system->copy("$test_dir/group.type.import.yml", "$sync_dir/group.type.import.yml");
    $file_system->copy("$test_dir/user.role.import.yml", "$sync_dir/user.role.import.yml");
    $file_system->copy("$test_dir/group.role.import-eea2d6f47.yml", "$sync_dir/group.role.import-eea2d6f47.yml");

    // Import the content of the sync directory.
    $this->configImporter()->import();

    // Check that the synchronized group roles give priority to the Yaml files.
    $group_role_id = $this->groupRoleSynchronizer->getGroupRoleId('import', 'import');
    /** @var \Drupal\group\Entity\GroupRoleInterface $from_yaml */
    $from_yaml = $this->entityTypeManager
      ->getStorage('group_role')
      ->load($group_role_id);
    $this->assertEquals(['view group'], $from_yaml->getPermissions(), 'Synchronized role was created from Yaml file.');

    // Check that synchronized group roles are being created without Yaml files.
    $group_roles = $this->entityTypeManager->getStorage('group_role')->loadMultiple();
    $group_role_id = $this->groupRoleSynchronizer->getGroupRoleId('import', $role->id());
    $this->assertArrayHasKey($group_role_id, $group_roles, 'Synchronized role found for imported group type');
  }

}
