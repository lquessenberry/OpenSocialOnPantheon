<?php

namespace Drupal\Tests\group\Kernel;

use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;

/**
 * Tests the import or synchronization of group type entities.
 *
 * @group group
 */
class GroupTypeImportTest extends EntityKernelTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = ['group', 'group_test_config'];

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The content enabler plugin manager.
   *
   * @var \Drupal\group\Plugin\GroupContentEnablerManagerInterface
   */
  protected $pluginManager;

  /**
   * A dummy group type.
   *
   * @var \Drupal\group\Entity\GroupTypeInterface
   */
  protected $groupType;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->entityTypeManager = $this->container->get('entity_type.manager');
    $this->pluginManager = $this->container->get('plugin.manager.group_content_enabler');

    $this->installConfig(['group']);
    $this->installEntitySchema('group');
    $this->installEntitySchema('group_type');
    $this->installEntitySchema('group_content');
    $this->installEntitySchema('group_content_type');
  }

  /**
   * Tests special behavior during group type import.
   */
  public function testImport() {
    // Simulate config data to import.
    $active = $this->container->get('config.storage');
    $sync = $this->container->get('config.storage.sync');
    $this->copyConfig($active, $sync);

    // Manually add the 'import' group type to the synchronization directory.
    $test_dir = __DIR__ . '/../../modules/group_test_config/sync';
    $sync_dir = config_get_config_directory(CONFIG_SYNC_DIRECTORY);
    $this->assertNotFalse(file_unmanaged_copy("$test_dir/group.type.import.yml", "$sync_dir/group.type.import.yml"), 'Copied the group type Yaml file to the sync dir.');

    // Import the content of the sync directory.
    $this->configImporter()->import();

    // Check that the group type was created.
    /** @var \Drupal\group\Entity\GroupTypeInterface $group_type */
    $group_type = $this->entityTypeManager
      ->getStorage('group_type')
      ->load('import');

    $this->assertNotNull($group_type, 'Import group type from sync was created.');

    // Check that no special group roles were created.
    $group_role_ids = [
      $group_type->getAnonymousRoleId(),
      $group_type->getOutsiderRoleId(),
      $group_type->getMemberRoleId(),
    ];

    $group_roles = $this->entityTypeManager
      ->getStorage('group_role')
      ->loadMultiple($group_role_ids);

    $this->assertEquals(0, count($group_roles), 'No special group roles were created.');

    // Check that no enforced plugins were installed.
    /** @var \Drupal\group\Plugin\GroupContentEnablerInterface $plugin */
    $plugin_config = ['group_type_id' => 'import', 'id' => 'group_membership'];
    $plugin = $this->pluginManager->createInstance('group_membership', $plugin_config);

    $group_content_type = $this->entityTypeManager
      ->getStorage('group_content_type')
      ->load($plugin->getContentTypeConfigId());

    $this->assertNull($group_content_type, 'No enforced plugins were installed.');
  }

}
