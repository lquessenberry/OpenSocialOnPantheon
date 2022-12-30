<?php

namespace Drupal\Tests\update_helper\Kernel;

use Drupal\Core\Serialization\Yaml;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\node\Traits\ContentTypeCreationTrait;

/**
 * @covers \Drupal\update_helper\Updater
 *
 * @group update_helper
 *
 * @package Drupal\Tests\update_helper\Kernel
 */
class UpdaterTest extends KernelTestBase {
  use ContentTypeCreationTrait;

  /**
   * Config directory path.
   *
   * @var string
   */
  protected $configDir = '';

  /**
   * Module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler = NULL;

  /**
   * Theme handler service.
   *
   * @var \Drupal\Core\Extension\ThemeHandlerInterface
   */
  protected $themeHandler;

  /**
   * Following configurations will be manipulated during testing.
   *
   * @var string[]
   */
  protected static $configSchemaCheckerExclusions = [
    'field.storage.node.body',
  ];

  /**
   * Modules to enable for test.
   *
   * @var array
   */
  protected static $modules = [
    'config_update',
    'update_helper',
    'update_helper_test',
    'system',
    'user',
    'text',
    'field',
    'node',
    'tour',
  ];

  /**
   * Get update definition that should be executed.
   *
   * @return array
   *   Update definition array.
   */
  protected function getUpdateDefinition() {
    return [
      '__global_actions' => [
        'install_modules' => [
          'help',
        ],
        'install_themes' => [
          'seven',
        ],
        'import_configs' => [
          'tour.tour.tour-update-helper-test',
        ],
      ],
      'field.storage.node.body' => [
        'expected_config' => [
          'lost_config' => 'text',
          'settings' => [
            'max_length' => 123,
          ],
          'status' => FALSE,
        ],
        'update_actions' => [
          'add' => [
            'cardinality' => 1,
          ],
          'change' => [
            'settings' => [],
            'status' => TRUE,
          ],
          'delete' => [
            'lost_config' => 'text',
            'settings' => [
              'max_length' => '123',
            ],
          ],
        ],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function setUpFilesystem() {
    // Use a real file system and not VFS so that we can create a fake module.
    // See \Drupal\KernelTests\Core\File\FileTestBase::setUpFilesystem().
    $public_file_directory = $this->siteDirectory . '/files';

    require_once 'core/includes/file.inc';

    mkdir($this->siteDirectory, 0775);
    mkdir($this->siteDirectory . '/files', 0775);
    mkdir($this->siteDirectory . '/files/config/sync', 0775, TRUE);

    $this->setSetting('file_public_path', $public_file_directory);
    $this->setSetting('config_sync_directory', $this->siteDirectory . '/files/config/sync');

    // Make a module for testing.
    $module_dir = $this->siteDirectory . '/modules/update_helper_test';
    mkdir($module_dir, 0755, TRUE);
    $info = [
      'name' => 'Update Helper test module',
      'type' => 'module',
      'core_version_requirement' => '*',
      'package' => 'Testing',
    ];
    file_put_contents($module_dir . '/update_helper_test.info.yml', Yaml::encode($info));
  }

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->moduleHandler = \Drupal::moduleHandler();
    $this->themeHandler = \Drupal::service('theme_handler');
    $module_dir = $this->moduleHandler->getModule('update_helper_test')->getPath();
    mkdir($module_dir . '/config/install', 0755, TRUE);
    // Prepare config file for testing of configuration import.
    $tour_config = [
      'id' => 'tour-update-helper-test',
      'module' => 'update_helper',
      'label' => 'Tour test Update Helper config import',
      'langcode' => 'en',
      'routes' => [
        ['route_name' => 'update_helper.1'],
      ],
      'tips' => [
        'tour-update-helper-test-1' => [
          'id' => 'update-helper-test-1',
          'plugin' => 'text',
          'label' => 'Update Helper',
          'body' => 'Update helper test tour.',
          'weight' => 1,
        ],
      ],
    ];

    file_put_contents($module_dir . '/config/install/tour.tour.tour-update-helper-test.yml', Yaml::encode($tour_config));

    /** @var \Drupal\update_helper\ConfigHandler $config_handler */
    $config_handler = \Drupal::service('update_helper.config_handler');

    // Create update configuration for testExecuteUpdate.
    $patch_file_path = $config_handler->getPatchFile('update_helper_test', 'test_updater', TRUE);
    file_put_contents($patch_file_path, Yaml::encode($this->getUpdateDefinition()));

    // Create update configuration for testOnlyDeleteUpdate.
    $patch_file_path = $config_handler->getPatchFile('update_helper_test', 'test_updater_only_delete', TRUE);
    file_put_contents($patch_file_path, Yaml::encode(
      [
        'field.storage.node.body' => [
          'expected_config' => [],
          'update_actions' => [
            'delete' => [
              'lost_config' => 'text',
            ],
          ],
        ],
      ]
    ));
    $this->installEntitySchema('node');
  }

  /**
   * @covers \Drupal\update_helper\Updater::executeUpdate
   */
  public function testExecuteUpdate() {
    /** @var \Drupal\config_update\ConfigRevertInterface $config_reverter */
    $config_reverter = \Drupal::service('config_update.config_update');
    $config_reverter->import('field_storage_config', 'node.body');

    /** @var \Drupal\Core\Config\ConfigFactory $config_factory */
    $config_factory = \Drupal::service('config.factory');
    $config = $config_factory->getEditable('field.storage.node.body');

    $expected_config_data = $config->get();

    $config_data = $config->get();
    $config_data['status'] = FALSE;
    unset($config_data['cardinality']);
    $config_data['settings'] = ['max_length' => 123];
    $config_data['lost_config'] = 'text';

    $config->setData($config_data)->save(TRUE);

    /** @var \Drupal\update_helper\Updater $update_helper */
    $update_helper = \Drupal::service('update_helper.updater');

    $this->assertFalse($this->moduleHandler->moduleExists('help'), 'Module "help" should not be installed.');
    $this->assertFalse($this->themeHandler->themeExists('seven'), 'Theme "seven" should not be installed.');

    // Create some configuration file for tour, so that it can be imported.
    $this->assertEquals(NULL, $config_factory->get('tour.tour.tour-update-helper-test')->get('id'), 'Tour configuration should not exist.');

    // Ensure that configuration had new values.
    $this->assertEquals('text', $config_factory->get('field.storage.node.body')->get('lost_config'));

    $update_helper->executeUpdate('update_helper_test', 'test_updater');

    $this->assertEquals($expected_config_data, $this->container->get('config.factory')->get('field.storage.node.body')->get());
    $this->assertTrue($this->moduleHandler->moduleExists('help'), 'Module "help" should be installed.');
    $this->assertTrue($this->themeHandler->themeExists('seven'), 'Theme "seven" should be installed.');
    $this->assertEquals('tour-update-helper-test', $this->container->get('config.factory')->get('tour.tour.tour-update-helper-test')->get('id'), 'Tour configuration should exist.');
  }

  /**
   * Test issue with using delete action without expected.
   */
  public function testOnlyDeleteUpdate() {
    /** @var \Drupal\config_update\ConfigRevertInterface $config_reverter */
    $config_reverter = \Drupal::service('config_update.config_update');
    $config_reverter->import('field_storage_config', 'node.body');

    $config = $this->config('field.storage.node.body');
    $expected_config_data = $config->get();

    $config_data = $expected_config_data;
    $config_data['lost_config'] = 'text';

    $config->setData($config_data)->save(TRUE);

    /** @var \Drupal\update_helper\Updater $update_helper */
    $update_helper = \Drupal::service('update_helper.updater');

    // Ensure that configuration had new values.
    $this->assertEquals('text', $this->config('field.storage.node.body')->get('lost_config'));

    // Execute update and validate new state.
    $update_helper->executeUpdate('update_helper_test', 'test_updater_only_delete');
    $this->assertEquals($expected_config_data, $this->config('field.storage.node.body')->get());
  }

  public function testConfigurationDependencies() {
    // Create an article content type this will add the body field to it.
    $this->installConfig(['node']);
    $this->createContentType(['type' => 'article']);

    // An entity display exists with dependencies on user and text.
    $this->assertEquals(['text', 'user'], $this->container->get('config.factory')->get('core.entity_view_display.node.article.default')->get('dependencies.module'));

    // Use the updater to remove the body field from the display and ensure the
    // dependencies are updated.
    /** @var \Drupal\update_helper\ConfigHandler $config_handler */
    $config_handler = \Drupal::service('update_helper.config_handler');
    $patch_file_path = $config_handler->getPatchFile('update_helper_test', 'test_updater_dependencies', TRUE);
    file_put_contents($patch_file_path, Yaml::encode(
      [
        'core.entity_view_display.node.article.default' => [
          'expected_config' => [],
          'update_actions' => [
            'delete' => [
              'content' => [
                'body' => [
                  'type' => 'text_default',
                  'label' => 'hidden',
                  'settings' => [],
                  'third_party_settings' => [],
                  'weight' => 101,
                  'region' => 'content',
                ],
              ],
            ],
          ],
        ],
      ]
    ));

    /** @var \Drupal\update_helper\Updater $update_helper */
    $update_helper = \Drupal::service('update_helper.updater');
    $update_helper->executeUpdate('update_helper_test', 'test_updater_dependencies');

    // An entity display exists with dependencies only on user.
    $this->assertEquals(['user'], $this->container->get('config.factory')->get('core.entity_view_display.node.article.default')->get('dependencies.module'));
  }

}
