<?php

namespace Drupal\ultimate_cron\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Logger\RfcLogLevel;
use Drupal\ultimate_cron\Entity\CronJob;

/**
 * A controller to interact with CronJob entities.
 */
class JobController extends ControllerBase {

  /**
   * Runs a single cron job.
   *
   * @param \Drupal\ultimate_cron\Entity\CronJob $ultimate_cron_job
   *   The cron job which will be run.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   Redirects to the job listing after running a job.
   */
  public function runCronJob(CronJob $ultimate_cron_job) {
    $ultimate_cron_job->run($this->t('Launched manually'));
    $this->messenger()
      ->addStatus($this->t('Cron job @job_label (@module) was successfully run.', [
        '@job_label' => $ultimate_cron_job->label(),
        '@module' => $ultimate_cron_job->getModuleName(),
      ]));
    return $this->redirect('entity.ultimate_cron_job.collection');
  }

  /**
   * Discovers new default cron jobs.
   */
  public function discoverJobs() {
    \Drupal::service('ultimate_cron.discovery')->discoverCronJobs();
    $this->messenger()
      ->addStatus($this->t('Completed discovery for new cron jobs.'));
    return $this->redirect('entity.ultimate_cron_job.collection');
  }

  /**
   * Displays a detailed cron job logs table.
   *
   * @param \Drupal\ultimate_cron\Entity\CronJob $ultimate_cron_job
   *   The cron job which will be run.
   *
   * @return array
   *   A render array as expected by drupal_render().
   */
  public function showLogs(CronJob $ultimate_cron_job) {

    $header = array(
      $this->t('Severity'),
      $this->t('Start Time'),
      $this->t('End Time'),
      $this->t('Message'),
      $this->t('Duration'),
    );

    $build['ultimate_cron_job_logs_table'] = [
      '#type' => 'table',
      '#header' => $header,
      '#empty' => $this->t('No log information available.'),
    ];

    $log_entries = $ultimate_cron_job->getLogEntries();
    foreach ($log_entries as $log_entry) {
      list($status, $title) = $log_entry->formatSeverity();
      $title = $log_entry->message ? $log_entry->message : $title;

      $row = [];
      $row['severity'] = $status;
      $row['severity']['#wrapper_attributes']['title'] = strip_tags($title);
      $row['start_time']['#markup'] = $log_entry->formatStartTime();
      $row['end_time']['#markup'] = $log_entry->formatEndTime();
      $row['message']['#markup'] = $log_entry->message ?: $log_entry->formatInitMessage();
      $row['duration']['#markup'] = $log_entry->formatDuration();

      $build['ultimate_cron_job_logs_table'][] = $row;
    }
    $build['#title'] = $this->t('Logs for %label', ['%label' => $ultimate_cron_job->label()]);
    return $build;

  }

  /**
   * Unlocks a single cron job.
   *
   * @param \Drupal\ultimate_cron\Entity\CronJob $ultimate_cron_job
   *   The cron job which will be unlocked.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   Redirects to the job listing after running a job.
   */
  public function unlockCronJob(CronJob $ultimate_cron_job) {
    $lock_id = $ultimate_cron_job->isLocked();
    $name = $ultimate_cron_job->label();

    // Unlock the process.
    if ($ultimate_cron_job->unlock($lock_id, TRUE)) {
      $user = \Drupal::currentUser();
      \Drupal::logger('ultimate_cron')->warning('@name manually unlocked by user @username (@uid)', array(
        '@name' => $ultimate_cron_job->id(),
        '@username' => $user->getDisplayName(),
        '@uid' => $user->id(),
      ));

      $this->messenger()
        ->addStatus($this->t('Cron job @name unlocked successfully.', [
          '@name' => $name,
        ]));
    }
    else {
      $this->messenger()
        ->addError($this->t('Could not unlock cron job @name', [
          '@name' => $name,
        ]));
    }

    return $this->redirect('entity.ultimate_cron_job.collection');
  }
}
