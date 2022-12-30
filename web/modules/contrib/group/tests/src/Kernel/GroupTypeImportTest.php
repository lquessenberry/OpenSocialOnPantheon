<?php

namespace Drupal\Tests\group\Kernel;

use Drupal\Core\Site\Settings;

/**
 * Tests the import or synchronization of group type entities.
 *
 * @coversDefaultClass \Drupal\group\Entity\GroupType
 * @group group
 */
class GroupTypeImportTest extends GroupKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    // The system.site key is required for import validation.
    // See: https://www.drupal.org/project/drupal/issues/2995062
    $this->installConfig(['system']);
  }

  /**
   * Tests special behavior during group type import.
   *
   * @covers ::postSave
   * @covers \Drupal\group\EventSubscriber\ConfigSubscriber::onConfigImport
   */
  public function testImport() {
    // Simulate config data to import.
    $active = $this->container->get('config.storage');
    $sync = $this->container->get('config.storage.sync');
    $this->copyConfig($active, $sync);

    // Manually add the 'import' group type to the synchronization directory.
    $test_dir = __DIR__ . '/../../modules/group_test_config/sync';
    $sync_dir = Settings::get('config_sync_directory');
    $file_system = $this->container->get('file_system');
    $file_system->copy("$test_dir/group.type.import.yml", "$sync_dir/group.type.import.yml");
    $file_system->copy("$test_dir/group.role.import-outsider.yml", "$sync_dir/group.role.import-outsider.yml");

    // Import the content of the sync directory.
    $this->configImporter()->import();

    // Check that the group type was created.
    /** @var \Drupal\group\Entity\GroupTypeInterface $group_type */
    $group_type = $this->entityTypeManager
      ->getStorage('group_type')
      ->load('import');
    $this->assertNotNull($group_type, 'Group type was loaded successfully.');

    // Check that the special group roles give priority to the Yaml files.
    /** @var \Drupal\group\Entity\GroupRoleInterface $outsider */
    $outsider = $this->entityTypeManager
      ->getStorage('group_role')
      ->load($group_type->getOutsiderRoleId());
    $this->assertEquals(['join group', 'view group'], $outsider->getPermissions(), 'Outsider role was created from Yaml file.');

    // Check that special group roles are being created without Yaml files.
    /** @var \Drupal\group\Entity\GroupRoleInterface $anonymous */
    $anonymous = $this->entityTypeManager
      ->getStorage('group_role')
      ->load($group_type->getAnonymousRoleId());
    $this->assertNotNull($anonymous, 'Anonymous role was created without a Yaml file.');

    // Check that enforced plugins were installed.
    /** @var \Drupal\group\Plugin\GroupContentEnablerInterface $plugin */
    $plugin_config = ['group_type_id' => 'import', 'id' => 'group_membership'];
    $plugin = $this->pluginManager->createInstance('group_membership', $plugin_config);
    $group_content_type = $this->entityTypeManager
      ->getStorage('group_content_type')
      ->load($plugin->getContentTypeConfigId());
    $this->assertNotNull($group_content_type, 'Enforced plugins were installed after config import.');
  }

}
