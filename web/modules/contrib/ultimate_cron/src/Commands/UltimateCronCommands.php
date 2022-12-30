<?php

namespace Drupal\ultimate_cron\Commands;

use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\ultimate_cron\CronPlugin;
use Drupal\ultimate_cron\Entity\CronJob;
use Drush\Commands\DrushCommands;

/**
 * Class UltimateCronCommands.
 *
 * @package Drupal\ultimate_cron\Commands
 */
class UltimateCronCommands extends DrushCommands {

  /**
   * Constructs an UltimateCronCommands object.
   *
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger
   *   Logger factory object.
   */
  public function __construct(LoggerChannelFactoryInterface $logger) {
    $this->logger = $logger->get('ultimate_cron');
  }

  /**
   * Show a cron jobs logs.
   *
   * @param string $name
   *   Job to show logs for.
   * @param array $options
   *   Options array.
   *
   * @command cron:logs
   *
   * @option limit Number of log entries to show
   * @option compact Only show the first line of each log entry
   * @usage drush cron-logs node_cron --limit=20
   *   Show 20 last logs for the node_cron job
   * @aliases cron-logs
   * @format table
   */
  public function logs($name, array $options = ['limit' => NULL, 'compact' => NULL]) {
    if (!$name) {
      throw new \Exception(dt('No job specified?'));
    }

    /** @var \Drupal\ultimate_cron\Entity\CronJob $job */
    $job = CronJob::load($name);

    if (!$job) {
      throw new \Exception(dt('@name not found', ['@name' => $name]));
    }

    $compact = $options['compact'];
    $limit = $options['limit'];
    $limit = $limit ? $limit : 10;

    $table = [];
    $table[] = [
      '',
      dt('Started'),
      dt('Duration'),
      dt('User'),
      dt('Initial message'),
      dt('Message'),
      dt('Status'),
    ];

    $lock_id = $job->isLocked();
    $log_entries = $job->getLogEntries(ULTIMATE_CRON_LOG_TYPE_ALL, $limit);

    /** @var \Drupal\ultimate_cron\Logger\LogEntry $log_entry */
    foreach ($log_entries as $log_entry) {
      $progress = '';
      if ($log_entry->lid && $lock_id && $log_entry->lid === $lock_id) {
        $progress = $job->getProgress();
        $progress = is_numeric($progress) ? sprintf(' (%d%%)', round($progress * 100)) : '';
      }

      $legend = '';
      if ($lock_id && $log_entry->lid == $lock_id) {
        $legend .= 'R';
        list(, $status) = $job->getPlugin('launcher')->formatRunning($job);
      }
      elseif ($log_entry->start_time && !$log_entry->end_time) {
        list(, $status) = $job->getPlugin('launcher')->formatUnfinished($job);
      }
      else {
        list(, $status) = $log_entry->formatSeverity();
      }

      $table[$log_entry->lid][] = $legend;
      $table[$log_entry->lid][] = $log_entry->formatStartTime();
      $table[$log_entry->lid][] = $log_entry->formatDuration() . $progress;
      $table[$log_entry->lid][] = $log_entry->formatUser();
      if ($compact) {
        $table[$log_entry->lid][] = trim(reset(explode("\n", $log_entry->init_message)), "\n");
        $table[$log_entry->lid][] = trim(reset(explode("\n", $log_entry->message)), "\n");
      }
      else {
        $table[$log_entry->lid][] = trim($log_entry->init_message, "\n");
        $table[$log_entry->lid][] = trim($log_entry->message, "\n");
      }
      $table[$log_entry->lid][] = $status;
    }

    return new RowsOfFields($table);
  }

  /**
   * List cron jobs.
   *
   * @param array $options
   *   Options array.
   *
   * @command cron:list
   * @option module Comma separated list of modules to show jobs from
   * @option enabled Show enabled jobs
   * @option disabled Show enabled jobs
   * @option behind Show jobs that are behind schedule
   * @option status Comma separated list of statuses to show jobs from
   * @option extended Show extended information
   * @option name Show name instead of title
   * @option scheduled Show scheduled jobs
   * @usage drush cron-list --status=running --module=node
   *   Show jobs from the node module that are currently running
   * @aliases crl cron-list
   * @format table
   */
  public function cronList(
    array $options = [
      'module' => NULL,
      'enabled' => NULL,
      'disabled' => NULL,
      'behind' => NULL,
      'status' => NULL,
      'extended' => NULL,
      'name' => NULL,
      'scheduled' => NULL,
    ]
  ) {
    $modules = $options['module'];
    $enabled = $options['enabled'];
    $disabled = $options['disabled'];
    $behind = $options['behind'];
    $extended = $options['extended'];
    $statuses = $options['status'];
    $scheduled = $options['scheduled'];
    $showname = $options['name'];

    $modules = $modules ? explode(',', $modules) : [];
    $statuses = $statuses ? explode(',', $statuses) : [];

    $title = $showname ? dt('Name') : dt('Title');

    $table = [];
    $table[] = [
      '',
      dt('ID'),
      dt('Module'),
      $title,
      dt('Scheduled'),
      dt('Started'),
      dt('Duration'),
      dt('Status'),
    ];

    $print_legend = FALSE;

    /** @var \Drupal\ultimate_cron\Entity\CronJob $job */
    foreach (CronJob::loadMultiple() as $name => $job) {
      if ($modules && !in_array($job->getModule(), $modules)) {
        continue;
      }

      if ($enabled && FALSE === $job->status()) {
        continue;
      }

      if ($disabled && TRUE === $job->status()) {
        continue;
      }

      if ($scheduled && !$job->isScheduled()) {
        continue;
      }

      $legend = '';

      if (FALSE === $job->status()) {
        $legend .= 'D';
        $print_legend = TRUE;
      }

      $lock_id = $job->isLocked();
      $log_entry = $job->loadLogEntry($lock_id);

      if ($time = $job->isBehindSchedule()) {
        $legend .= 'B';
        $print_legend = TRUE;
      }

      if ($behind && !$time) {
        continue;
      }

      if ($lock_id && $log_entry->lid == $lock_id) {
        $legend .= 'R';
        list(, $status) = $job->getPlugin('launcher')->formatRunning($job);
        $print_legend = TRUE;
      }
      elseif ($log_entry->start_time && !$log_entry->end_time) {
        list(, $status) = $job->getPlugin('launcher')->formatUnfinished($job);
      }
      else {
        list(, $status) = $log_entry->formatSeverity();
      }

      if ($statuses && !in_array($status, $statuses)) {
        continue;
      }

      $progress = $lock_id ? $job->formatProgress() : '';

      $table[$name][] = $legend;
      $table[$name][] = $job->id();
      $table[$name][] = $job->getModuleName();
      $table[$name][] = $showname ? $job->id() : $job->getTitle();
      $table[$name][] = $job->getPlugin('scheduler')->formatLabel($job);
      $table[$name][] = $log_entry->formatStartTime();
      $table[$name][] = $log_entry->formatDuration() . ' ' . $progress;
      $table[$name][] = $status;

      if ($extended) {
        $table['extended:' . $name][] = '';
        $table['extended:' . $name][] = '';
        $table['extended:' . $name][] = $job->id();
        $table['extended:' . $name][] = $job->getPlugin('scheduler')->formatLabelVerbose($job);
        $table['extended:' . $name][] = $log_entry->init_message;
        $table['extended:' . $name][] = $log_entry->message;
      }
    }

    if ($print_legend) {
      $this->output->writeln("\n" . dt('Legend: D = Disabled, R = Running, B = Behind schedule'));
    }

    return new RowsOfFields($table);
  }

  /**
   * Run cron job.
   *
   * @param string $name
   *   Job to run.
   * @param array $options
   *   Options array.
   *
   * @command cron:run
   *
   * @option force Skip the schedule check for each job. Locks are still respected.
   * @option options Custom options for plugins, e.g. --options=thread=1 for serial launcher
   * @usage drush cron-run node_cron
   *   Run the node_cron job
   * @aliases crun cron-run
   */
  public function run($name = NULL, array $options = ['force' => NULL, 'options' => NULL]) {
    if ($o = $options['options']) {
      $pairs = explode(',', $o);
      foreach ($pairs as $pair) {
        list($key, $value) = explode('=', $pair);
        CronPlugin::setGlobalOption(trim($key), trim($value));
      }
    }

    $force = $options['force'];

    if (!$name) {
      throw new \Exception(dt("Running all cronjobs is not supported by Ultimate Cron's cron:run - please use Drupal Core's core:cron command!"));
    }

    // Run a specific job.
    $job = CronJob::load($name);

    if (!$job) {
      throw new \Exception(dt('@name not found', ['@name' => $name]));
    }

    if ($force || $job->isScheduled()) {
      $job->run(t('Launched by drush'));
    }

  }

  /**
   * Enable cron job.
   *
   * @param string $name
   *   Job to enable.
   * @param array $options
   *   Options array.
   *
   * @command cron:enable
   *
   * @option all Enabled all jobs
   * @usage drush cron-enable node_cron
   *   Enable the node_cron job
   * @aliases cre cron-enable
   */
  public function enable($name, array $options = ['all' => NULL]) {
    if (!$name) {
      if (!$options['all']) {
        throw new \Exception(dt('No job specified?'));
      }
      /** @var \Drupal\ultimate_cron\Entity\CronJob $job */
      foreach (CronJob::loadMultiple() as $job) {
        $job->enable()->save();
      }
      return;
    }

    $job = CronJob::load($name);
    if ($job->enable()->save()) {
      $this->output->writeln(dt('@name enabled', ['@name' => $name]));
    }
  }

  /**
   * Disable cron job.
   *
   * @param string $name
   *   Job to disable.
   * @param array $options
   *   Options array.
   *
   * @command cron:disable
   *
   * @option all Enabled all jobs
   * @usage drush cron-disable node_cron
   *   Disable the node_cron job
   * @aliases crd cron-disable
   */
  public function disable($name, array $options = ['all' => NULL]) {
    if (!$name) {
      if (!$options['all']) {
        throw new \Exception(dt('No job specified?'));
      }
      foreach (CronJob::loadMultiple() as $job) {
        $job->disable()->save();
      }
      return;
    }

    $job = CronJob::load($name);
    if ($job->disable()->save()) {
      $this->output->writeln(dt('@name disabled', ['@name' => $name]));
    }
  }

  /**
   * Unlock cron job.
   *
   * @param string $name
   *   Job to unlock.
   * @param array $options
   *   Options array.
   *
   * @command cron:unlock
   *
   * @option all Enabled all jobs
   * @usage drush cron-unlock node_cron
   *   Unlock the node_cron job
   * @aliases cru cron-unlock
   */
  public function unlock($name, array $options = ['all' => NULL]) {
    if (!$name) {
      if (!$options['all']) {
        throw new \Exception(dt('No job specified?'));
      }
      /** @var \Drupal\ultimate_cron\Entity\CronJob $job */
      foreach (CronJob::loadMultiple() as $job) {
        if ($job->isLocked()) {
          $job->unlock();
        }
      }
      return;
    }

    /** @var \Drupal\ultimate_cron\Entity\CronJob $job */
    $job = CronJob::load($name);
    if (!$job) {
      throw new \Exception(dt('@name not found', ['@name' => $name]));
    }

    $lock_id = $job->isLocked();
    if (!$lock_id) {
      throw new \Exception(dt('@name is not running', ['@name' => $name]));
    }

    // Unlock the process.
    if ($job->unlock($lock_id, TRUE)) {
      $log_entry = $job->resumeLog($lock_id);
      global $user;
      $this->logger->warning('@name manually unlocked by user @username (@uid)', [
        '@name' => $job->id(),
        '@username' => $user->getDisplayName(),
        '@uid' => $user->id(),
      ]);
      $log_entry->finish();

      $this->output->writeln(dt('Cron job @name unlocked', ['@name' => $name]));
    }
    else {
      throw new \Exception(dt('Could not unlock cron job @name', ['@name' => $name]));
    }
  }

}
