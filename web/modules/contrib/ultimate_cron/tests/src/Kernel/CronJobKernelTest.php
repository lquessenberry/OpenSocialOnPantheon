<?php

namespace Drupal\Tests\ultimate_cron\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\ultimate_cron\Entity\CronJob;

/**
 * Tests CRUD for cron jobs.
 *
 * @group ultimate_cron
 */
class CronJobKernelTest extends KernelTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = array('system', 'ultimate_cron');

  protected function setup(): void {
    parent::setUp();

    $this->installSchema('ultimate_cron', [
      'ultimate_cron_log',
      'ultimate_cron_lock',
    ]);
  }

  /**
   * Tests CRUD operations for cron jobs.
   */
  public function testCRUD() {
    $values = array(
      'id' => 'example',
      'title' => $this->randomMachineName(),
      'description' => $this->randomMachineName(),
    );

    /** @var \Drupal\ultimate_cron\Entity\CronJob $cron_job */
    $cron_job = CronJob::create($values);
    $cron_job->save();

    $this->assertEquals('example', $cron_job->id());
    $this->assertEquals($values['title'], $cron_job->label());
    $this->assertTrue($cron_job->status());

    $cron_job->disable();
    $cron_job->save();

    $cron_job = CronJob::load('example');
    $this->assertEquals('example', $cron_job->id());
    $this->assertFalse($cron_job->status());
  }

}
