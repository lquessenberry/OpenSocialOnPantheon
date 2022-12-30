<?php

namespace Drupal\Tests\ultimate_cron\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\ultimate_cron\CronRule;
use Drupal\ultimate_cron\Entity\CronJob;

/**
 * Cron Job Testing if a job runs when it is supposed to.
 *
 * @group ultimate_cron
 */
class CronJobTest extends KernelTestBase {

  public static $modules = array('ultimate_cron');

  public function setup(): void {
    parent::setUp();

    $this->installSchema('ultimate_cron', array(
        'ultimate_cron_log',
        'ultimate_cron_lock'
      ));
  }

  /**
   * Tests adding and editing a cron job.
   */
  function testGeneratedJob() {
    $values = array(
      'title' => 'ultimate cron fake cronjob title',
      'id' => 'ultimate_cron_fake_job',
      'module' => 'ultimate_cron_fake',
      'callback' => 'ultimate_cron_fake_cron',
    );

    $job = CronJob::create($values);
    $job->save();

    // Check the latest log entry, there should be none.
    $this->assertEquals(0, CronJob::load($values['id'])->loadLatestLogEntry()->start_time);

    // Check run counter, at this point there should be 0 runs.
    $this->assertEquals(0, \Drupal::state()->get('ultimate_cron.cron_run_counter'));

    // Run cron manually for the first time.
    \Drupal::service('cron')->run();

    // Check run counter, at this point there should be 1 run.
    $this->assertEquals(1, \Drupal::state()->get('ultimate_cron.cron_run_counter'));

    // Generate an initial scheduled cron time.
    $cron = CronRule::factory('*/15+@ * * * *', time(), $job->getUniqueID() & 0xff);
    $scheduled_cron_time = $cron->getLastSchedule();

    // Load Latest log entry time.
    $latest_log_entry = CronJob::load($values['id'])->loadLatestLogEntry()->start_time;

    // Latest log entry should not be 0 because it ran already.
    $this->assertNotEquals(0, $latest_log_entry);

    // Generate a new start time by adding two seconds to the initial scheduled cron time.
    $log_entry_future = $scheduled_cron_time + 2;

    // Update new start_time in the future so the next cron job should not run.
    \Drupal::database()->update('ultimate_cron_log')
      ->fields(array('start_time' => $log_entry_future))
      ->condition('name', $values['id'])
      ->execute();

    // Cron job should not run.
    \Drupal::service('cron')->run();

    // Load latest log entry.
    $cron_last_ran = CronJob::load($values['id'])->loadLatestLogEntry()->start_time;

    // Check if job ran, it shouldn't have.
    $this->assertEquals($log_entry_future, $cron_last_ran);
    $this->assertEquals(1, \Drupal::state()->get('ultimate_cron.cron_run_counter'));

    // Generate a new start time by deducting two seconds from the initial scheduled cron time.
    $log_entry_past = $scheduled_cron_time - 2;

    // Update new start_time in the past so the next cron job should run.
    \Drupal::database()->update('ultimate_cron_log')
      ->fields(array('start_time' => $log_entry_past))
      ->condition('name', $values['id'])
      ->execute();

    // Cron job should run.
    \Drupal::service('cron')->run();

    // Check if the cron job has run, it should have.
    $this->assertNotEquals($log_entry_past, $latest_log_entry);
    $this->assertEquals(2, \Drupal::state()->get('ultimate_cron.cron_run_counter'));
  }
}
