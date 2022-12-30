<?php

namespace Drupal\Tests\update_helper\Kernel;

use Drupal\Component\Serialization\Yaml;
use Drupal\KernelTests\KernelTestBase;
use Drupal\update_helper\ConfigHandler;
use Drush\TestTraits\DrushTestTrait;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Automated tests for ConfigName class.
 *
 * @group update_helper
 *
 * @covers \Drupal\update_helper\ConfigHandler
 */
class ConfigHandlerTest extends KernelTestBase {
  use DrushTestTrait;

  /**
   * An array of config object names that are excluded from schema checking.
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
    'user',
    'text',
    'field',
    'node',
  ];

  /**
   * Returns update definition data.
   *
   * @return string
   *   Update definition Yaml string.
   */
  public static function getUpdateDefinition() {
    return <<<EOF
field.storage.node.body:
  expected_config:
    lost_config: text
    settings:
      max_length: 123
    status: false
    type: text
  update_actions:
    delete:
      lost_config: text
      settings:
        max_length: 123
    add:
      cardinality: 1
    change:
      settings: {  }
      status: true
      type: text_with_summary

EOF;
  }

  /**
   * The sha1 of configuration file that is modified during testing.
   *
   * @var string
   */
  protected $startingSha1;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    /** @var \Drupal\Core\Config\FileStorage $extensionStorage */
    $extensionStorage = \Drupal::service('config_update.extension_storage');
    $this->startingSha1 = sha1_file($extensionStorage->getFilePath('field.storage.node.body'));
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

    // Copy the node module so we can modify config for testing.
    $file_system = new Filesystem();
    $file_system->mirror('core/modules/node', $this->siteDirectory . '/modules/node');
  }

  /**
   * @covers \Drupal\update_helper\ConfigHandler::generatePatchFile
   */
  public function testGeneratePatchFileFromActiveConfig() {
    /** @var \Drupal\update_helper\ConfigHandler $configHandler */
    $configHandler = \Drupal::service('update_helper.config_handler');

    /** @var \Drupal\config_update\ConfigRevertInterface $configReverter */
    $configReverter = \Drupal::service('config_update.config_update');
    $configReverter->import('field_storage_config', 'node.body');

    /** @var \Drupal\Core\Config\ConfigFactory $configFactory */
    $configFactory = \Drupal::service('config.factory');
    $config = $configFactory->getEditable('field.storage.node.body');
    $configData = $config->get();
    $configData['status'] = FALSE;
    $configData['type'] = 'text';
    unset($configData['cardinality']);
    $configData['settings'] = ['max_length' => 123];
    $configData['lost_config'] = 'text';

    $config->setData($configData)->save(TRUE);

    // Generate patch after configuration change.
    $data = $configHandler->generatePatchFile(['node'], TRUE);

    $this->assertEquals($this->getUpdateDefinition(), $data);

    // Check that configuration file is not changed.
    /** @var \Drupal\Core\Config\FileStorage $extensionStorage */
    $extensionStorage = \Drupal::service('config_update.extension_storage');
    $this->assertSame($this->startingSha1, sha1_file($extensionStorage->getFilePath('field.storage.node.body')));
  }

  /**
   * @covers \Drupal\update_helper\ConfigHandler::generatePatchFile
   */
  public function testGeneratePatchFileWithConfigExport() {
    /** @var \Drupal\update_helper\ConfigHandler $configHandler */
    $configHandler = \Drupal::service('update_helper.config_handler');

    /** @var \Drupal\Core\Config\FileStorage $extensionStorage */
    $extensionStorage = \Drupal::service('config_update.extension_storage');
    $configFilePath = $extensionStorage->getFilePath('field.storage.node.body');

    /** @var \Drupal\config_update\ConfigRevertInterface $configReverter */
    $configReverter = \Drupal::service('config_update.config_update');
    $configReverter->import('field_storage_config', 'node.body');

    /** @var \Drupal\Core\Config\ConfigFactory $configFactory */
    $configFactory = \Drupal::service('config.factory');
    $config = $configFactory->getEditable('field.storage.node.body');
    $configData = $config->get();

    $configData['type'] = 'text';
    $configData['settings'] = ['max_length' => 321];
    $config->setData($configData)->save(TRUE);

    // Check file configuration before export.
    $fileData = Yaml::decode(file_get_contents($configFilePath));
    $this->assertSame('text_with_summary', $fileData['type']);
    $this->assertSame([], $fileData['settings']);

    // Generate patch and export config after configuration change.
    $data = $configHandler->generatePatchFile(['node'], FALSE);

    $expected = <<<EOF
field.storage.node.body:
  expected_config:
    settings: {  }
    type: text_with_summary
  update_actions:
    change:
      settings:
        max_length: 321
      type: text

EOF;
    $this->assertSame($expected, $data);

    // Check newly exported configuration.
    $fileData = Yaml::decode(file_get_contents($configFilePath));

    $this->assertSame('text', $fileData['type']);
    $this->assertSame(['max_length' => 321], $fileData['settings']);
  }

  /**
   * @covers \Drupal\update_helper\ConfigHandler::getPatchFile
   */
  public function testGetPatchFileSerializerSupport() {
    $configList = \Drupal::service('config_update.config_list');
    $configReverter = \Drupal::service('config_update.config_update');
    $configDiffer = \Drupal::service('update_helper.config_differ');
    $configDiffTransformer = \Drupal::service('update_helper.config_diff_transformer');
    $moduleHandler = \Drupal::service('module_handler');
    $configExporter = \Drupal::service('update_helper.config_exporter');

    $configHandlerYaml = new ConfigHandler($configList, $configReverter, $configDiffer, $configDiffTransformer, $moduleHandler, \Drupal::service('serialization.yaml'), $configExporter);
    $this->assertStringEndsWith('config_handler_test.yml', $configHandlerYaml->getPatchFile('update_helper', 'config_handler_test'));

    $configHandlerJson = new ConfigHandler($configList, $configReverter, $configDiffer, $configDiffTransformer, $moduleHandler, \Drupal::service('serialization.json'), $configExporter);
    $this->assertStringEndsWith('config_handler_test.json', $configHandlerJson->getPatchFile('update_helper', 'config_handler_test'));

    $configHandlerPhpSerialize = new ConfigHandler($configList, $configReverter, $configDiffer, $configDiffTransformer, $moduleHandler, \Drupal::service('serialization.phpserialize'), $configExporter);
    $this->assertStringEndsWith('config_handler_test.serialized', $configHandlerPhpSerialize->getPatchFile('update_helper', 'config_handler_test'));
  }

}
