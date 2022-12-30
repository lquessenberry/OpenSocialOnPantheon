<?php

namespace Drupal\Tests\update_helper_checklist\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\update_helper_checklist\Entity\Update;

/**
 * @covers \Drupal\update_helper_checklist\UpdateChecklist
 *
 * @group update_helper_checklist
 *
 * @requires module checklistapi
 *
 * @package Drupal\Tests\update_helper_checklist\Kernel
 */
class UpdateChecklistTest extends KernelTestBase {

  /**
   * Modules to enable for test.
   *
   * @var array
   */
  protected static $modules = [
    'system',
    'user',
    'config_update',
    'update_helper',
    'checklistapi',
    'update_helper_checklist',
  ];

  /**
   * Install entity schema for Update entity.
   */
  protected function setUp() {
    parent::setUp();

    $this->installEntitySchema('update_helper_checklist_update');
  }

  /**
   * Get update checklist service.
   *
   * @return \Drupal\update_helper_checklist\UpdateChecklist
   *   Returns update checklist service.
   */
  protected function getUpdateChecklist() {
    return \Drupal::service('update_helper_checklist.update_checklist');
  }

  /**
   * @covers \Drupal\update_helper_checklist\UpdateChecklist::getUpdateVersions
   */
  public function testGetUpdateVersions() {
    $update_checklist = $this->getUpdateChecklist();

    // Get update versions when module is not installed yet.
    $update_versions = $update_checklist->getUpdateVersions('update_helper_checklist_test');
    $this->assertEquals([], $update_versions);

    // After module is installed.
    $this->container->get('module_installer')
      ->install(['update_helper_checklist_test']);
    $update_versions = $update_checklist->getUpdateVersions('update_helper_checklist_test');
    $this->assertEquals(['v8.x-1.0', 'v8.x-1.1'], $update_versions);
  }

  /**
   * Test update hook: update_helper_checklist_modules_installed.
   */
  public function testModulesInstalledHook() {
    $first_update_checklist_entry = Update::load('update_helper_checklist_test:update_helper_checklist_test_update_8001');

    $this->assertEquals(NULL, $first_update_checklist_entry);

    $this->container->get('module_installer')
      ->install(['update_helper_checklist_test']);
    $all_saved = Update::loadMultiple();
    $expected_all_saved_ids = [
      'update_helper_checklist_test:update_helper_checklist_test_update_8001',
      'update_helper_checklist_test:update_helper_checklist_test_update_8002',
      'update_helper_checklist_test:update_helper_checklist_test_update_8003',
    ];

    $this->assertEquals($expected_all_saved_ids, array_keys($all_saved));

    /** @var \Drupal\Core\KeyValueStore\KeyValueFactoryInterface $key_value_factory */
    $key_value_factory = $this->container->get('keyvalue');

    $state = $key_value_factory->get('state')->get('checklistapi.progress.update_helper_checklist');
    foreach ($expected_all_saved_ids as $update_id) {
      $this->assertNotEmpty($state['#items'][$update_id]);
    }
  }

}
