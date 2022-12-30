<?php

namespace Drupal\Tests\update_helper\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\update_helper\ConfigName;

/**
 * Automated tests for ConfigName class.
 *
 * @group update_helper
 *
 * @covers \Drupal\update_helper\ConfigName
 */
class ConfigNameTest extends KernelTestBase {

  /**
   * Modules to enable for test.
   *
   * @var array
   */
  protected static $modules = ['views', 'field'];

  /**
   * Test creation of class by full name.
   *
   * @param string $fullName
   *   Full configuration name.
   * @param string $type
   *   Configuration type.
   * @param string $name
   *   Configuration name.
   *
   * @dataProvider configNameDataProvider
   */
  public function testCreateByFullName($fullName, $type, $name) {
    $configName = ConfigName::createByFullName($fullName);

    $this->assertEquals($type, $configName->getType());
    $this->assertEquals($name, $configName->getName());
  }

  /**
   * Test creation of class by type and name.
   *
   * @param string $fullName
   *   Full configuration name.
   * @param string $type
   *   Configuration type.
   * @param string $name
   *   Configuration name.
   *
   * @dataProvider configNameDataProvider
   */
  public function testCreateByTypeName($fullName, $type, $name) {
    $configName = ConfigName::createByTypeName($type, $name);

    $this->assertEquals($fullName, $configName->getFullName());
  }

  /**
   * Data provider for configuration name tests.
   *
   * @return array
   *   Test data with full name + type and name of configuration.
   */
  public function configNameDataProvider() {
    return [
      [
        'core.entity_view_display.test.display.config',
        'entity_view_display',
        'test.display.config',
      ],
      [
        'core.extension',
        'system.simple',
        'core.extension',
      ],
      [
        'views.view.test',
        'view',
        'test',
      ],
      [
        'field.field.taxonomy_term.test.field_test',
        'field_config',
        'taxonomy_term.test.field_test',
      ],
      [
        'field.storage.taxonomy_term.field_test',
        'field_storage_config',
        'taxonomy_term.field_test',
      ],
    ];
  }

}
