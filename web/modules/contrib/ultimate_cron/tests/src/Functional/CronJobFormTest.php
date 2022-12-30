<?php

namespace Drupal\Tests\ultimate_cron\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\Traits\Core\CronRunTrait;
use Drupal\ultimate_cron\Entity\CronJob;

/**
 * Cron Job Form Testing.
 *
 * @group ultimate_cron
 */
class CronJobFormTest extends BrowserTestBase {

  use CronRunTrait;

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = array('ultimate_cron', 'block', 'cron_queue_test');

  /**
   * A user with permission to create and edit books and to administer blocks.
   *
   * @var \Drupal\user\Entity\User
   */
  protected $adminUser;

  /**
   * Cron job name.
   *
   * @var string
   */
  protected $jobName;

  /**
   * Cron job machine id.
   *
   * @var string
   */
  protected $jobId = 'system_cron';

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'classy';

  /**
   * Tests adding and editing a cron job.
   */
  public function testManageJob() {
    $this->drupalPlaceBlock('local_tasks_block');
    $this->drupalPlaceBlock('local_actions_block');

    // Create user with correct permission.
    $this->adminUser = $this->drupalCreateUser(array('administer ultimate cron', 'administer site configuration'));
    $this->drupalLogin($this->adminUser);

    // Cron Jobs overview.
    $this->drupalGet('admin/config/system/cron/jobs');
    $this->assertSession()->statusCodeEquals('200');

    // Check for the default schedule message in Job list.
    $this->assertSession()->pageTextContains('Every 15 min');
    // Check for the Last Run default value.
    $this->assertSession()->pageTextContains('Never');

    // Start editing added job.
    $this->drupalGet('admin/config/system/cron/jobs/manage/' . $this->jobId);
    $this->assertSession()->statusCodeEquals('200');

    // Set new cron job configuration and save the old job name.
    $job = CronJob::load($this->jobId);
    $old_job_name = $job->label();
    $this->jobName = 'edited job name';
    $edit = array('title' => $this->jobName);

    // Save the new job.
    $this->submitForm($edit, t('Save'));
    // Assert the edited Job hasn't run yet.
    $this->assertSession()->pageTextContains('Never');
    // Assert messenger service message for successful updated job.
    $this->assertSession()->pageTextContains(t('job @name has been updated.', array('@name' => $this->jobName)));

    // Run the Jobs.
    $this->cronRun();

    // Assert the cron jobs have been run by checking the time.
    $this->drupalGet('admin/config/system/cron/jobs');
    $this->assertSession()->pageTextContains(\Drupal::service('date.formatter')->format(\Drupal::state()->get('system.cron_last'), 'short'));

    // Check that all jobs have been run.
    $this->assertSession()->pageTextNotContains("Never");

    // Assert cron job overview for recently updated job.
    $this->drupalGet('admin/config/system/cron/jobs');
    $this->assertSession()->pageTextNotContains($old_job_name);
    $this->assertSession()->pageTextContains($this->jobName);

    // Change time when cron runs, check the 'Scheduled' label is updated.
    $this->clickLink(t('Edit'));
    $this->submitForm(['scheduler[configuration][rules][0]' => '0+@ */6 * * *'], t('Save'));
    $this->assertSession()->pageTextContains('Every 6 hours');

    // Test disabling a job.
    $this->clickLink(t('Disable'), 0);
    $this->assertSession()->pageTextContains('This cron job will no longer be executed.');
    $this->submitForm([], t('Disable'));

    // Assert messenger service message for successful disabled job.
    $this->assertSession()->pageTextContains(t('Disabled cron job @name.', array('@name' => $this->jobName)));
    $this->drupalGet('admin/config/system/cron/jobs');
    $this->assertSession()->elementExists('xpath', '//table/tbody/tr[1]/td[6]');
    $this->assertSession()->elementContains('xpath','//table/tbody/tr[1]/td[8]/div/div/ul/li[1]/a', 'Enable');
    $this->assertSession()->elementNotContains('xpath', '//table/tbody/tr[1]/td[8]/div/div/ul/li[1]/a', 'Run');

    // Test enabling a job.
    $this->clickLink(t('Enable'), 0);
    $this->assertSession()->pageTextContains('This cron job will be executed again.');
    $this->submitForm([], t('Enable'));

    // Assert messenger service message for successful enabled job.
    $this->assertSession()->pageTextContains(t('Enabled cron job @name.', array('@name' => $this->jobName)));
    $this->drupalGet('admin/config/system/cron/jobs');
    $expected_checkmark_image_url = \Drupal::service('file_url_generator')->generateString('core/misc/icons/73b355/check.svg');
    $this->assertEquals($expected_checkmark_image_url, $this->xpath('//table/tbody/tr[1]/td[6]/img')[0]->getAttribute('src'));
    $this->assertSession()->elementExists('xpath','//table/tbody/tr[1]/td[8]/div/div/ul/li[1]/a');

    // Test disabling a job with the checkbox on the edit page.
    $edit = array(
      'status' => FALSE,
    );
    $this->drupalGet('admin/config/system/cron/jobs/manage/' . $this->jobId);
    $this->submitForm($edit, t('Save'));
    $this->assertSession()->elementExists('xpath','//table/tbody/tr[1]/td[6]');
    $this->assertSession()->elementContains('xpath','//table/tbody/tr[1]/td[8]/div/div/ul/li[1]/a', 'Enable');
    $this->assertSession()->elementNotContains('xpath', '//table/tbody/tr[1]/td[8]/div/div/ul/li[1]/a', 'Run');

    // Test enabling a job with the checkbox on the edit page.
    $edit = array(
      'status' => TRUE,
    );
    $this->drupalGet('admin/config/system/cron/jobs/manage/' . $this->jobId);
    $this->submitForm($edit, t('Save'));
    $this->assertEquals($expected_checkmark_image_url, $this->xpath('//table/tbody/tr[1]/td[6]/img')[0]->getAttribute('src'));
    $this->assertSession()->elementExists('xpath','//table/tbody/tr[1]/td[8]/div/div/ul/li[1]/a');

    $this->drupalGet('admin/config/system/cron/jobs');

    // Save new job.
    $this->clickLink(t('Edit'), 0);
    $job_configuration = array(
      'scheduler[id]' => 'crontab',
    );
    $this->submitForm($job_configuration, t('Save'));
    $this->drupalGet('admin/config/system/cron/jobs/manage/' . $this->jobId);
    $this->submitForm(['scheduler[configuration][rules][0]' => '0+@ * * * *'], t('Save'));
    $this->assertSession()->pageTextContains('0+@ * * * *');

    // Try editing the rule to an invalid one.
    $this->clickLink('Edit');
    $this->submitForm(['scheduler[configuration][rules][0]' => '*//15+@ *-2 * * *'], t('Save'));
    $this->assertSession()->pageTextContains('Rule is invalid');
    $this->assertSession()->titleEquals('Edit job | Drupal');

    // Assert that there is no Delete link on the details page.
    $this->assertSession()->linkNotExists('Delete');

    // Force a job to be invalid by changing the callback.
    $job = CronJob::load($this->jobId);
    $job->setCallback('non_existing_function')
      ->save();
    $this->drupalGet('admin/config/system/cron/jobs');

    // Assert that the invalid cron job is displayed properly.
    $this->assertSession()->elementExists('xpath','//table/tbody/tr[1]/td[6]');
    $this->assertSession()->elementExists('xpath','//table/tbody/tr[1]/td[8]/div/div/ul/li/a');

    // Test deleting a job (only possible if invalid cron job).
    $this->clickLink(t('Delete'), 0);
    $this->submitForm([], t('Delete'));
    $this->assertSession()->pageTextContains(t('The cron job @name has been deleted.', array('@name' => $job->label())));
    $this->drupalGet('admin/config/system/cron/jobs');
    $this->assertSession()->pageTextNotContains($job->label());

    $job = CronJob::load('ultimate_cron_cron');

    $log_entries = $job->getLogEntries();
    $log_entry = reset($log_entries);

    // Test logs details page.
    $this->drupalGet('admin/config/system/cron/jobs');
    $this->clickLink('Logs');
    $xpath = $this->xpath('//tbody/tr[@class="odd"]/td');
    $start_time = \Drupal::service('date.formatter')->format((int) $log_entry->start_time, 'custom', 'Y-m-d H:i:s');
    $end_time = \Drupal::service('date.formatter')->format((int) $log_entry->end_time, 'custom', 'Y-m-d H:i:s');
    $this->assertEquals($xpath[1]->getText(), $start_time);
    $this->assertEquals($xpath[2]->getText(), $end_time);
    // The message logged depends on timing, do not hardcode that.
    $this->assertEquals($xpath[3]->getText(), $log_entry->message ?: $log_entry->formatInitMessage());
    $this->assertEquals($xpath[4]->getText(), '00:00');

    // Assert queue cron jobs.
    $this->config('ultimate_cron.settings')
      ->set('queue.enabled', TRUE)
      ->save();

    \Drupal::service('ultimate_cron.discovery')->discoverCronJobs();
    $this->drupalGet('admin/config/system/cron/jobs');
    $this->assertSession()->pageTextContains('Queue: Broken queue test');

    $this->drupalGet('admin/config/system/cron/jobs/manage/ultimate_cron_queue_cron_queue_test_broken_queue');
    $this->assertSession()->fieldValueEquals('title', 'Queue: Broken queue test');
    $this->submitForm([], 'Save');
    $this->assertSession()->pageTextContains('job Queue: Broken queue test has been updated.');
  }

}
