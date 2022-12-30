<?php

namespace Drupal\Tests\ultimate_cron\Functional;

use Drupal\Core\Url;
use Drupal\Tests\BrowserTestBase;
use Drupal\ultimate_cron\CronRule;
use Drupal\ultimate_cron\Entity\CronJob;

/**
 * Cron Job Form Testing
 *
 * @group ultimate_cron
 */
class CronJobInstallTest extends BrowserTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = array('ultimate_cron');

  /**
   * A user with permission to create and edit books and to administer blocks.
   *
   * @var \Drupal\user\Entity\User
   */
  protected $adminUser;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests adding and editing a cron job.
   */
  public function testManageJob() {
    // Create user with correct permission.
    $this->adminUser = $this->drupalCreateUser(array('administer ultimate cron'));
    $this->drupalLogin($this->adminUser);

    // Check default modules
    \Drupal::service('module_installer')->install(array('field'));
    $this->drupalGet('admin/config/system/cron/jobs');
    $this->assertSession()->pageTextContains('Purges deleted Field API data');
    $this->assertSession()->pageTextContains('Cleanup (caches, batch, flood, temp-files, etc.)');
    $this->assertSession()->pageTextNotContains('Deletes temporary files');

    // Install new module.
    \Drupal::service('module_installer')->install(array('file'));
    $this->drupalGet('admin/config/system/cron/jobs');
    $this->assertSession()->pageTextContains('Deletes temporary files');

    // Uninstall new module.
    \Drupal::service('module_installer')->uninstall(array('file'));
    $this->drupalGet('admin/config/system/cron/jobs');
    $this->assertSession()->pageTextNotContains('Deletes temporary files');
  }

  /**
   * Tests the requirements checking of ultimate_cron.
   */
  public function testRequirements() {
    $element = ultimate_cron_requirements('runtime')['cron_jobs'];
    $this->assertEquals($element['value'], t("Cron is running properly."));
    $this->assertEquals($element['severity'], REQUIREMENT_OK);


    $values = array(
      'title' => 'ultimate cron fake cronjob title',
      'id' => 'ultimate_cron_fake_job',
      'module' => 'ultimate_cron_fake',
      'callback' => 'ultimate_cron_fake_cron',
    );

    $job = CronJob::create($values);
    $job->save();

    \Drupal::service('cron')->run();

    // Generate an initial scheduled cron time.
    $cron = CronRule::factory('*/15+@ * * * *', time(), $job->getUniqueID() & 0xff);
    $scheduled_cron_time = $cron->getLastSchedule();
    // Generate a new start time by adding two seconds to the initial scheduled cron time.
    $log_entry_past = $scheduled_cron_time - 10000;
      \Drupal::database()->update('ultimate_cron_log')
      ->fields([
        'start_time' => $log_entry_past,
      ])
      ->condition('name', $values['id'])
      ->execute();

    // Check run counter, at this point there should be 0 run.
    $this->assertEquals(1, \Drupal::state()->get('ultimate_cron.cron_run_counter'), 'Job has run once.');
    $this->assertNotEmpty($job->isBehindSchedule(), 'Job is behind schedule.');

    $element = ultimate_cron_requirements('runtime')['cron_jobs'];
    $this->assertEquals($element['value'], '1 job is behind schedule', '"1 job is behind schedule." is displayed');
    $this->assertEquals($element['description']['#markup'], 'Some jobs are behind their schedule. Please check if <a href="' .
      Url::fromRoute('system.cron', ['key' => \Drupal::state()->get('system.cron_key')])->toString() .
      '">Cron</a> is running properly.', 'Description is correct.');
    $this->assertEquals($element['severity'], REQUIREMENT_WARNING, 'Severity is of level "Error"');
  }

}
