<?php
/**
 * @file
 * Drush commands for Ultimate Cron!
 */
use Drupal\ultimate_cron\CronPlugin;
use Drupal\ultimate_cron\Entity\CronJob;

/**
 * Implements hook_drush_command().
 */
function ultimate_cron_drush_command() {
  $items = array();

  $items['cron-logs'] = array(
    'description' => 'Show a cron jobs logs',
    'arguments' => array(
      'name' => 'Job to show logs for',
    ),
    'options' => array(
      'limit' => 'Number of log entries to show',
      'compact' => 'Only show the first line of each log entry',
    ),
    'examples' => array(
      'drush cron-logs node_cron --limit=20' => 'Show 20 last logs for the node_cron job',
    ),
  );

  $items['cron-list'] = array(
    'description' => 'List cron jobs',
    'options' => array(
      'module' => 'Comma separated list of modules to show jobs from',
      'enabled' => 'Show enabled jobs',
      'disabled' => 'Show enabled jobs',
      'behind' => 'Show jobs that are behind schedule',
      'status' => 'Comma separated list of statuses to show jobs from',
      'extended' => 'Show extended information',
      'name' => 'Show name instead of title',
      'scheduled' => 'Show scheduled jobs',
    ),
    'examples' => array(
      'drush cron-list --status=running --module=node' => 'Show jobs from the node module that are currently running',
    ),
    'aliases' => array('cl'),
  );

  $items['cron-run'] = array(
    'description' => 'Run cron job',
    'arguments' => array(
      'name' => 'Job to run',
    ),
    'options' => array(
      'force' => 'Skip the schedule check for each job. Locks are still respected.',
      'options' => 'Custom options for plugins, e.g. --options=thread=1 for serial launcher',
    ),
    'examples' => array(
      'drush cron-run node_cron' => 'Run the node_cron job',
      'drush cron-run --options=thread=1' => 'Run all scheduled jobs and instruct serial launcher only to launch thread 1 jobs',
    ),
    'aliases' => array('crun'),
  );

  $items['cron-is-running'] = array(
    'description' => 'Tell whether cron is running. Exit status is set in concordance with the cron running status.',
    'examples' => array(
      'drush cron-is-running' => 'Check if cron is running.',
      'drush cron-is-running --quiet' => 'Check if cron is running and don\'t show an informative message.',
      'while `drush cron-is-running --quiet`; do echo "Waiting cron to finish"; sleep 1; done' => 'Bash loop to wait until cron finishes.',
    ),
    'aliases' => array('cir'),
  );

  $items['cron-enable'] = array(
    'description' => 'Enable cron job',
    'arguments' => array(
      'name' => 'Job to enable',
    ),
    'options' => array(
      'all' => 'Enabled all jobs',
    ),
    'examples' => array(
      'drush cron-enable node_cron' => 'Enable the node_cron job',
    ),
    'aliases' => array('ce'),
  );

  $items['cron-disable'] = array(
    'description' => 'Disable cron job',
    'arguments' => array(
      'name' => 'Job to disable',
    ),
    'options' => array(
      'all' => 'Enabled all jobs',
    ),
    'examples' => array(
      'drush cron-disable node_cron' => 'Disable the node_cron job',
    ),
    'aliases' => array('cd'),
  );

  $items['cron-unlock'] = array(
    'description' => 'Unlock cron job',
    'arguments' => array(
      'name' => 'Job to unlock',
    ),
    'options' => array(
      'all' => 'Enabled all jobs',
    ),
    'examples' => array(
      'drush cron-unlock node_cron' => 'Unlock the node_cron job',
    ),
    'aliases' => array('cu'),
  );

  return $items;
}

/**
 * Implements hook_drush_help().
 */
function ultimate_cron_drush_help($section) {
  switch ($section) {
    case 'drush:cron-list':
      return dt('This command will list cron jobs');

    case 'drush:cron-run':
      return dt('This command will run a cron job');

    case 'drush:cron-enable':
      return dt('This command will enable a cron job');

    case 'drush:cron-disable':
      return dt('This command will disable a cron job');

    case 'drush:cron-unlock':
      return dt('This command will unlock a cron job');
  }
}

/**
 * List cron jobs.
 */
function drush_ultimate_cron_cron_list() {
  $modules = drush_get_option('module');
  $enabled = drush_get_option('enabled');
  $disabled = drush_get_option('disabled');
  $behind = drush_get_option('behind');
  $extended = drush_get_option('extended');
  $statuses = drush_get_option('status');
  $scheduled = drush_get_option('scheduled');
  $showname = drush_get_option('name');

  $modules = $modules ? explode(',', $modules) : array();
  $statuses = $statuses ? explode(',', $statuses) : array();

  $title = $showname ? dt('Name') : dt('Title');

  $table = array();
  $table[] = array(
    '',
    dt('ID'),
    dt('Module'),
    $title,
    dt('Scheduled'),
    dt('Started'),
    dt('Duration'),
    dt('Status'),
  );

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
  drush_print_table($table);
  if ($print_legend) {
    drush_print("\n" . dt('Legend: D = Disabled, R = Running, B = Behind schedule'));
  }
}

/**
 * List cron jobs.
 */
function drush_ultimate_cron_cron_logs($name = NULL) {
  if (!$name) {
    return drush_set_error(dt('No job specified?'));
  }

  /** @var CronJob $job */
  $job = Cronjob::load($name);

  if (!$job) {
    return drush_set_error(dt('@name not found', array('@name' => $name)));
  }

  $compact = drush_get_option('compact');
  $limit = drush_get_option('limit');
  $limit = $limit ? $limit : 10;

  $table = array();
  $table[] = array(
    '',
    dt('Started'),
    dt('Duration'),
    dt('User'),
    dt('Initial message'),
    dt('Message'),
    dt('Status'),
  );

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
  drush_print_table($table);
}

/**
 * Run cron job(s).
 */
function drush_ultimate_cron_cron_run($name = NULL) {

  if ($options = drush_get_option('options')) {
    $pairs = explode(',', $options);
    foreach ($pairs as $pair) {
      list($key, $value) = explode('=', $pair);
      CronPlugin::setGlobalOption(trim($key), trim($value));
    }
  }

  $force = drush_get_option('force');

  if (!$name) {
    // Run all jobs.
    $jobs = CronJob::loadMultiple();

    /** @var CronJob $job */
    foreach($jobs as $job) {
      if ($force || $job->isScheduled()) {
        $job->run(t('Launched by drush'));
      }

    }
  }
  else {
    // Run a specific job.
    $job = CronJob::load($name);

    if (!$job) {
      return drush_set_error(dt('@name not found', array('@name' => $name)));
    }

    if ($force || $job->isScheduled()) {
      $job->run(t('Launched by drush'));
    }
  }
}

/**
 * Tell whether cron is running.
 */
function drush_ultimate_cron_cron_is_running() {
  $locked = FALSE;
  foreach (CronJob::loadMultiple() as $name => $job) {
    if ($job->isLocked()) {
      $locked = TRUE;
      break;
    }
  }

  if ($locked) {
    $msg = dt('Cron is running.');
    drush_set_context('DRUSH_EXIT_CODE', 0);
  }
  else {
    $msg = dt('Cron is not running.');
    drush_set_context('DRUSH_EXIT_CODE', 1);
  }

  return $msg;
}

/**
 * Enable a cron job.
 */
function drush_ultimate_cron_cron_enable($name = NULL) {
  if (!$name) {
    if (!drush_get_option('all')) {
      return drush_set_error(dt('No job specified?'));
    }
    /** @var CronJob $job */
    foreach (CronJob::loadMultiple() as $job) {
      $job->enable()->save();
    }
    return;
  }

  $job = CronJob::load($name);
  if ($job->enable()->save()) {
    drush_print(dt('@name enabled', array('@name' => $name)));
  }
}

/**
 * Disable a cron job.
 */
function drush_ultimate_cron_cron_disable($name = NULL) {
  if (!$name) {
    if (!drush_get_option('all')) {
      return drush_set_error(dt('No job specified?'));
    }
    foreach (CronJob::loadMultiple() as $job) {
      $job->disable()->save();
    }
    return;
  }

  $job = CronJob::load($name);
  if ($job->disable()->save()) {
    drush_print(dt('@name disabled', array('@name' => $name)));
  }
}

/**
 * Unlock a cron job.
 */
function drush_ultimate_cron_cron_unlock($name = NULL) {
  if (!$name) {
    if (!drush_get_option('all')) {
      return drush_set_error(dt('No job specified?'));
    }
    /** @var CronJob $job */
    foreach (CronJob::loadMultiple() as $job) {
      if ($job->isLocked()) {
        $job->unlock();
      }
    }
    return;
  }

  /** @var CronJob $job */
  $job = CronJob::load($name);
  if (!$job) {
    return drush_set_error(dt('@name not found', array('@name' => $name)));
  }

  $lock_id = $job->isLocked();
  if (!$lock_id) {
    return drush_set_error(dt('@name is not running', array('@name' => $name)));
  }

  // Unlock the process.
  if ($job->unlock($lock_id, TRUE)) {
    $log_entry = $job->resumeLog($lock_id);
    global $user;
    \Drupal::logger('ultimate_cron')->warning('@name manually unlocked by user @username (@uid)', array(
      '@name' => $job->id(),
      '@username' => $user->getDisplayName(),
      '@uid' => $user->id(),
    ));
    $log_entry->finish();

    drush_print(dt('Cron job @name unlocked', array('@name' => $name)));
  }
  else {
    drush_set_error(dt('Could not unlock cron job @name', array('@name' => $name)));
  }
}
