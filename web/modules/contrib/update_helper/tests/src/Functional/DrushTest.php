<?php

namespace Drupal\Tests\update_helper\Functional;

use Drupal\Component\Serialization\Yaml;
use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\update_helper\Kernel\ConfigHandlerTest;
use Drush\TestTraits\DrushTestTrait;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Automated tests for Drush commands.
 *
 * Note this has to be a functional test for \Drush\TestTraits\DrushTestTrait to
 * work.
 *
 * @group update_helper
 *
 * @covers \Drupal\update_helper\ConfigHandler
 */
class DrushTest extends BrowserTestBase {
  use DrushTestTrait;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $configSchemaCheckerExclusions = [
    'field.storage.node.body',
  ];

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'update_helper',
    'node',
    'test_node_config',
  ];

  /**
   * {@inheritdoc}
   */
  protected function prepareEnvironment() {
    parent::prepareEnvironment();
    mkdir($this->siteDirectory . '/modules/test_node_config', 0775, TRUE);
    $info = [
      'name' => 'Node config test module',
      'type' => 'module',
      'core_version_requirement' => '*',
      'package' => 'Testing',
    ];
    file_put_contents($this->siteDirectory . '/modules/test_node_config/test_node_config.info.yml', Yaml::encode($info));
  }

  /**
   * Tests `drush generate configuration-update`.
   *
   * @dataProvider generatePatchFileFromActiveConfigUsingDrushProvider
   */
  public function testGeneratePatchFileFromActiveConfigUsingDrush(string $answers, string $update_file, string $install_file) {

    // Copy the node module so we can modify config for testing.
    $file_system = new Filesystem();
    $file_system->mirror('core/modules/node/config/install', $this->siteDirectory . '/modules/test_node_config/config/install');

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

    $install_file = $this->siteDirectory . '/modules/test_node_config/' . $install_file;
    $update_file = $this->siteDirectory . '/modules/test_node_config/config/update/' . $update_file;
    $this->assertFileDoesNotExist($install_file);
    $this->assertFileDoesNotExist($update_file);

    $this->drush('generate', ['config-update'], [], NULL, NULL, 0, "--answer test_node_config $answers --answer 'Some description' --answer test_node_config --answer Yes --yes", ['SHELL_INTERACTIVE' => 1]);
    $this->assertFileExists($install_file);
    $this->assertFileExists($update_file);
    $this->assertEquals(ConfigHandlerTest::getUpdateDefinition(), file_get_contents($update_file));
    include_once $install_file;
    $this->assertTrue(function_exists(basename($update_file, '.yml')), basename($update_file, '.yml') . '() function exists');
  }

  /**
   * Provider function for testGeneratePatchFileFromActiveConfigUsingDrush().
   */
  public function generatePatchFileFromActiveConfigUsingDrushProvider() {
    return [
      [
        '--answer hook_update_N --answer 9001',
        'test_node_config_update_9001.yml',
        'test_node_config.install',
      ],
      [
        '--answer post_update --answer test',
        'test_node_config_post_update_0001_test.yml',
        'test_node_config.post_update.php',
      ],
    ];
  }


  /**
   * Tests `drush generate configuration-update`.
   *
   * @dataProvider generatePatchFileFromActiveConfigUsingDrushExistingUpdatesProvider
   */
  public function testGeneratePatchFileFromActiveConfigUsingDrushExistingUpdates(string $answers, string $update_file, string $install_file, string $install_file_contents) {

    // Copy the node module so we can modify config for testing.
    $file_system = new Filesystem();
    $file_system->mirror('core/modules/node/config/install', $this->siteDirectory . '/modules/test_node_config/config/install');

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

    $install_file = $this->siteDirectory . '/modules/test_node_config/' . $install_file;
    $update_file = $this->siteDirectory . '/modules/test_node_config/config/update/' . $update_file;
    file_put_contents($install_file, $install_file_contents);
    $this->assertFileDoesNotExist($update_file);

    $this->drush('generate', ['config-update'], [], NULL, NULL, 0, "--answer test_node_config $answers --answer 'Some description' --answer test_node_config --answer Yes --yes", ['SHELL_INTERACTIVE' => 1]);
    $this->assertFileExists($update_file);
    $this->assertEquals(ConfigHandlerTest::getUpdateDefinition(), file_get_contents($update_file));
    include_once $install_file;
    $this->assertTrue(function_exists(basename($update_file, '.yml')), basename($update_file, '.yml') . '() function exists');
  }

  /**
   * Provider function for testGeneratePatchFileFromActiveConfigUsingDrush().
   */
  public function generatePatchFileFromActiveConfigUsingDrushExistingUpdatesProvider() {
    return [
      [
        '--answer post_update --answer test',
        'test_node_config_post_update_0001_test.yml',
        'test_node_config.post_update.php',
        "<?php\n",
      ],
      [
        '--answer post_update --answer test',
        'test_node_config_post_update_0002_test.yml',
        'test_node_config.post_update.php',
        "<?php\nfunction test_node_config_post_update_0001_test() {}",
      ],
      [
        '--answer post_update --answer test',
        'test_node_config_post_update_1002_test.yml',
        'test_node_config.post_update.php',
        "<?php\nfunction test_node_config_removed_post_updates() { return ['test_node_config_post_update_1001_test' => '9.3.2']; }",
      ],
      [
        '--answer post_update --answer test',
        'test_node_config_post_update_8052_test.yml',
        'test_node_config.post_update.php',
        "<?php\nfunction test_node_config_removed_post_updates() { return ['test_node_config_post_update_1001_test' => '9.3.2']; }\nfunction test_node_config_post_update_8051_test() {}",
      ],
    ];
  }

}
