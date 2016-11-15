<?php

namespace Drupal\Tests\group\Kernel;

use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;

/**
 * Tests the creation of group type entities during extension install.
 *
 * @group group
 */
class GroupTypeInstallTest extends EntityKernelTestBase {

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
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->entityTypeManager = $this->container->get('entity_type.manager');
    $this->pluginManager = $this->container->get('plugin.manager.group_content_enabler');

    $this->installConfig(['group', 'group_test_config']);
    $this->installEntitySchema('group');
    $this->installEntitySchema('group_type');
    $this->installEntitySchema('group_content');
    $this->installEntitySchema('group_content_type');
  }

  /**
   * Tests special behavior during group type creation.
   */
  public function testInstall() {
    // Check that the group type was created and saved properly.
    /** @var \Drupal\group\Entity\GroupTypeInterface $group_type */
    $group_type = $this->entityTypeManager
      ->getStorage('group_type')
      ->load('default');

    $this->assertNotNull($group_type, 'Group type was loaded successfully.');

    // Check that the special group roles give priority to the Yaml files.
    $outsider = $group_type->getOutsiderRole();
    $this->assertEquals(['join group', 'view group'], $outsider->getPermissions(), 'Outsider role was created from Yaml file.');

    // Check that special group roles are being created without Yaml files.
    /** @var \Drupal\group\Entity\GroupRoleInterface $group_role */
    $group_role = $this->entityTypeManager
      ->getStorage('group_role')
      ->load($group_type->getAnonymousRoleId());

    $this->assertNotNull($group_role, 'Anonymous role was created without a Yaml file.');

    // Check that the enforced plugins give priority to the Yaml files.
    /** @var \Drupal\group\Plugin\GroupContentEnablerInterface $plugin */
    $plugin = $group_type->getContentPlugin('group_membership');
    $config = $plugin->getConfiguration();

    $this->assertEquals('<p>test</p>', $config['info_text']['value'], 'Enforced group_membership plugin was created from Yaml file.');
  }

}
