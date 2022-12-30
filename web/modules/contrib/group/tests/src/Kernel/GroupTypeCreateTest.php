<?php

namespace Drupal\Tests\group\Kernel;

use Drupal\group\Entity\GroupTypeInterface;

/**
 * Tests the creation of group type entities.
 *
 * @coversDefaultClass \Drupal\group\Entity\GroupType
 * @group group
 */
class GroupTypeCreateTest extends GroupKernelTestBase {

  /**
   * Tests special behavior during group type creation.
   *
   * @covers ::postSave
   */
  public function testCreate() {
    /** @var \Drupal\group\Entity\Storage\GroupContentTypeStorageInterface $group_content_type_storage */
    $group_content_type_storage = $this->entityTypeManager->getStorage('group_content_type');
    $this->assertCount(2, $group_content_type_storage->loadByEntityTypeId('user'));

    // Check that the group type was created and saved properly.
    /** @var \Drupal\group\Entity\GroupTypeInterface $group_type */
    $group_type = $this->entityTypeManager
      ->getStorage('group_type')
      ->create([
        'id' => 'dummy',
        'label' => 'Dummy',
        'description' => $this->randomMachineName(),
      ]);

    $this->assertInstanceOf(GroupTypeInterface::class, $group_type);
    $this->assertEquals(SAVED_NEW, $group_type->save(), 'Group type was saved successfully.');

    // Check that the special group roles were created.
    $group_role_ids = [
      $group_type->getAnonymousRoleId(),
      $group_type->getOutsiderRoleId(),
      $group_type->getMemberRoleId(),
    ];

    $group_roles = $this->entityTypeManager
      ->getStorage('group_role')
      ->loadMultiple($group_role_ids);

    $this->assertEquals(3, count($group_roles), 'Three special roles were created.');

    // Check that enforced plugins were installed.
    /** @var \Drupal\group\Plugin\GroupContentEnablerInterface $plugin */
    $plugin_config = ['group_type_id' => 'dummy', 'id' => 'group_membership'];
    $plugin = $this->pluginManager->createInstance('group_membership', $plugin_config);

    $this->assertCount(3, $group_content_type_storage->loadByEntityTypeId('user'));
    $group_content_type = $group_content_type_storage->load($plugin->getContentTypeConfigId());
    $this->assertNotNull($group_content_type, 'Enforced plugins were installed on the group type.');
  }

}
