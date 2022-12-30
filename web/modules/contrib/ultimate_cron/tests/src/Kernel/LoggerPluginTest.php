<?php

namespace Drupal\Tests\ultimate_cron\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\ultimate_cron\Entity\CronJob;
use Drupal\ultimate_cron\Logger\LogEntry;
use Drupal\ultimate_cron\Plugin\ultimate_cron\Logger\CacheLogger;
use Drupal\ultimate_cron\Plugin\ultimate_cron\Logger\DatabaseLogger;

/**
 * Tests the default scheduler plugins.
 *
 * @group ultimate_cron
 */
class LoggerPluginTest extends KernelTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = array('ultimate_cron', 'ultimate_cron_logger_test', 'system');

  /**
   * Tests that scheduler plugins are discovered correctly.
   */
  function testDiscovery() {
    /* @var \Drupal\Core\Plugin\DefaultPluginManager $manager */
    $manager = \Drupal::service('plugin.manager.ultimate_cron.logger');

    $plugins = $manager->getDefinitions();
    $this->assertCount(2, $plugins);

    $cache = $manager->createInstance('cache');
    $this->assertTrue($cache instanceof CacheLogger);
    $this->assertEquals('cache', $cache->getPluginId());

    $database = $manager->createInstance('database');
    $this->assertTrue($database instanceof DatabaseLogger);
    $this->assertEquals('database', $database->getPluginId());
  }

  /**
   * Tests log cleanup of the database logger.
   */
  function testCleanup() {

    $this->installSchema('ultimate_cron', ['ultimate_cron_log', 'ultimate_cron_lock']);

    \Drupal::service('ultimate_cron.discovery')->discoverCronJobs();

    $job = CronJob::load('ultimate_cron_logger_test_cron');
    $job->setConfiguration('logger', [
      'retain' => 10,
    ]);
    $job->save();

    // Run the job 12 times.
    for ($i = 0; $i < 12; $i++) {
      $job->getPlugin('launcher')->launch($job);
    }

    // There are 12 run log entries and one from the modified job.
    $log_entries = $job->getLogEntries(ULTIMATE_CRON_LOG_TYPE_ALL, 15);
    $this->assertCount(13, $log_entries);

    // Run cleanup.
    ultimate_cron_cron();

    // There should be exactly 10 log entries now.
    $log_entries = $job->getLogEntries(ULTIMATE_CRON_LOG_TYPE_ALL, 15);
    $this->assertCount(10, $log_entries);

    // Switch to expire-based cleanup.
    $job->setConfiguration('logger', [
      'expire' => 60,
      'method' => DatabaseLogger::CLEANUP_METHOD_EXPIRE,
    ]);
    $job->save();

    $ids = array_slice(array_keys($log_entries), 5);

    // Date back 5 log entries.
      \Drupal::database()->update('ultimate_cron_log')
      ->expression('start_time', 'start_time - 65')
      ->condition('lid', $ids, 'IN')
      ->execute();

    // Run cleanup.
    ultimate_cron_cron();

    // There should be exactly 6 log entries now, as saving caused another
    // modified entry to be saved.
    $log_entries = $job->getLogEntries(ULTIMATE_CRON_LOG_TYPE_ALL, 15);
    $this->assertCount(6, $log_entries);
  }

  /**
   * Tests cache logger.
   */
  function testCacheLogger() {
    // @todo Set default logger and do not enable the log table.
    $this->installSchema('ultimate_cron', ['ultimate_cron_log', 'ultimate_cron_lock']);

    \Drupal::service('ultimate_cron.discovery')->discoverCronJobs();

    $job = CronJob::load('ultimate_cron_logger_test_cron');
    $job->setLoggerId('cache');
    $job->save();

    // Launch the job twice.
    $job->getPlugin('launcher')->launch($job);
    $job->getPlugin('launcher')->launch($job);

    // There is only one log entry.
    $log_entries = $job->getLogEntries(ULTIMATE_CRON_LOG_TYPE_ALL, 3);
    $this->assertCount(1, $log_entries);

    $log_entry = reset($log_entries);
    $this->assertTrue($log_entry instanceof LogEntry);
    $this->assertEquals('ultimate_cron_logger_test_cron', $log_entry->name);
    $this->assertEquals('Launched manually by anonymous (0)', (string) $log_entry->formatInitMessage());
  }

}
