<?php

namespace Drupal\Tests\ultimate_cron\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\ultimate_cron\Plugin\ultimate_cron\Scheduler\Crontab;
use Drupal\ultimate_cron\Plugin\ultimate_cron\Scheduler\Simple;

/**
 * Tests the default scheduler plugins.
 *
 * @group ultimate_cron
 */
class SchedulerPluginTest extends KernelTestBase {

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
    $manager = \Drupal::service('plugin.manager.ultimate_cron.scheduler');

    $plugins = $manager->getDefinitions();
    $this->assertCount(2, $plugins);

    $simple = $manager->createInstance('simple');
    $this->assertTrue($simple instanceof Simple);
    $this->assertEquals('simple', $simple->getPluginId());

    $crontab = $manager->createInstance('crontab');
    $this->assertTrue($crontab instanceof Crontab);
    $this->assertEquals('crontab', $crontab->getPluginId());
  }
}
