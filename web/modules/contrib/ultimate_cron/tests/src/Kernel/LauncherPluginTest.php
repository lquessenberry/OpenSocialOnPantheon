<?php

namespace Drupal\Tests\ultimate_cron\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\ultimate_cron\Plugin\ultimate_cron\Launcher\SerialLauncher;

/**
 * Tests the default scheduler plugins.
 *
 * @group ultimate_cron
 */
class LauncherPluginTest extends KernelTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = array('ultimate_cron');

  /**
   * Tests that scheduler plugins are discovered correctly.
   */
  function testDiscovery() {
    /* @var \Drupal\Core\Plugin\DefaultPluginManager $manager */
    $manager = \Drupal::service('plugin.manager.ultimate_cron.launcher');

    $plugins = $manager->getDefinitions();
    $this->assertCount(1, $plugins);

    $serial = $manager->createInstance('serial');
    $this->assertTrue($serial instanceof SerialLauncher);
    $this->assertEquals('serial', $serial->getPluginId());
  }
}
