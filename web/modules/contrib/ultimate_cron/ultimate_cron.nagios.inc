<?php
use Drupal\Core\Logger\RfcLogLevel;
use Drupal\ultimate_cron\CronJobDiscovery;

/**
 * Implements hook_nagios_info().
 */
function ultimate_cron_nagios_info() {
  return array(
    'name'   => t('Ultimate Cron Monitoring'),
    'id'     => 'ULTIMATE_CRON',
  );
}

/**
 * Implementation of hook_nagios().
 */
function ultimate_cron_nagios($check = 'nagios') {
  $status = array();
  foreach(ultimate_cron_nagios_functions() as $function => $description) {
    if (variable_get('ultimate_cron_nagios_func_' . $function, TRUE) && ($check == 'nagios' || $check == $function)) {
      $func = $function . '_check';
      $result = $func();
      $status[$result['key']] = $result['data'];
    }
  }

  return $status;
}

/**
 * Implementation of hook_nagios_settings().
 */
function ultimate_cron_nagios_settings() {
  $form = array();
  
  foreach(ultimate_cron_nagios_functions() as $function => $description) {
    $var = 'ultimate_cron_nagios_func_' . $function;
    $form[$var] = array(
      '#type'          => 'checkbox',
      '#title'         => $function,
      '#default_value' => variable_get($var, TRUE),
      '#description' => $description,
    );
  }
 
  $group = 'thresholds';
  $form[$group] = array(
    '#type'        => 'fieldset',
    '#collapsible' => TRUE,
    '#collapsed'   => FALSE,
    '#title'       => t('Thresholds'),
    '#description' => t('Thresholds for reporting critical alerts to Nagios.'),
  );

  $form[$group]['ultimate_cron_nagios_running_threshold'] = array(
    '#type'          => 'textfield',
    '#title'         => t('Running jobs count'),
    '#default_value' => variable_get('ultimate_cron_nagios_running_threshold', 50),
    '#description'   => t('Issue a critical alert when more than this number of jobs are running. Default is 50.'),
  );
  
  $form[$group]['ultimate_cron_nagios_failed_threshold'] = array(
    '#type'          => 'textfield',
    '#title'         => t('Failed jobs count'),
    '#default_value' => variable_get('ultimate_cron_nagios_failed_threshold', 10),
    '#description'   => t('Issue a critical alert when more than this number of jobs failed their last run. Default is 10.'),
  );
  
  $form[$group]['ultimate_cron_nagios_longrunning_threshold'] = array(
    '#type'          => 'textfield',
    '#title'         => t('Long running jobs'),
    '#default_value' => variable_get('ultimate_cron_nagios_longrunning_threshold', 0),
    '#description'   => t('Issue a critical alert when more than this number of jobs are running longer than usual. Default is 0.')
  );
  
  return $form;
}

/**
 * Implementation of hook_nagios_checks().
 */
function ultimate_cron_nagios_checks() {
  return ultimate_cron_nagios_functions();
}

/**
 * Implementation of drush hook_nagios_check().
 */
function ultimate_cron_nagios_check($function) {
  // We don't bother to check if the function has been enabled by the user.
  // Since this runs via drush, web security is not an issue.
  $func = $function . '_check';
  $result = $func();
  $status[$result['key']] = $result['data'];
  return $status;
}

/************** HELPER FUNCTIONS ***********************************/
/**
 * Return a list of nagios check functions 
 * @see ultimate_cron_nagios()
 */
function ultimate_cron_nagios_functions() {
  return array(
    'ultimate_cron_running' => t('Check number of currently running jobs'),
    'ultimate_cron_failed' => t('Check the number of jobs that failed last run'),
    'ultimate_cron_longrunning' => t('Check the number of jobs that are running longer than usual'),
  );
}

/**
 * Get information about running jobs - currently running or failed.
 *
 * @staticvar array $overview
 * @param string $mode Which mode to get info about; 'running' or 'error'
 * @return int 
 */
function ultimate_cron_nagios_get_job_info($mode = 'running') {
  // Ensure valid mode
  if (!in_array($mode, array('running', 'error'))) {
    $mode = 'running';
  }
  static $overview = array();

  if (!isset($overview[$mode])) {
    $overview[$mode] = 0;
    // Get hooks and their data
    $hooks = CronJobDiscovery::getHooks();

    $modules = array();
    foreach ($hooks as $name => $hook) {
      if (!$module || $module == $hook['module']) {
        $log = ultimate_cron_get_log($name);
        
        if ($hook['background_process']) {
          $overview['running']++;
        }
        $severity_type = $log['severity'] < 0 ? 'success' : ($log['severity'] >= RfcLogLevel::NOTICE ? 'info' : ($log['severity'] >= RfcLogLevel::NOTICE ? 'warning' : 'error'));
        $overview[$severity_type]++;
      }
    }
  }
  
  return $overview[$mode];
}

/*************** NAGIOS CHECK FUNCTIONS ********************************/
/**
 * Check number of running jobs.
 * 
 * @return array
 */
function ultimate_cron_running_check() {
  $running = ultimate_cron_nagios_get_job_info('running');
  $threshold = variable_get('ultimate_cron_nagios_running_threshold', 50);
  if (count($running) > $threshold) {
    $data = array(
      'status' => NAGIOS_STATUS_CRITICAL,
      'type'   => 'state',
      'text'   => t('@jobs currently running - it is more than @threshold', array('@jobs' => $running, '@threshold' => $threshold)),
    );
  }
  else {
    $data = array(
      'status' => NAGIOS_STATUS_OK, 
      'type'   => 'state',
      'text'   => t('@jobs currently running', array('@jobs' => $running)),
    );
  }

  return array(
    'key' => 'ULTIMATE_CRON_RUNNING',
    'data' => $data,
  );
}

/**
 * Check number of jobs that failed last run.
 * 
 * @return array
 */
function ultimate_cron_failed_check() {
  $failed = ultimate_cron_nagios_get_job_info('errors');
  $threshold = variable_get('ultimate_cron_nagios_failed_threshold', 10);
  if (count($failed) > $threshold) {
    $data = array(
      'status' => NAGIOS_STATUS_CRITICAL,
      'type'   => 'state',
      'text'   => t('@jobs failed their last run - it is more than @threshold', array('@jobs' => $failed, '@threshold' => $threshold)),
    );
  }
  else {
    $data = array(
      'status' => NAGIOS_STATUS_OK, 
      'type'   => 'state',
      'text'   => t('@jobs failed their last run', array('@jobs' => $failed)),
    );
  }

  return array(
    'key' => 'ULTIMATE_CRON_FAILED',
    'data' => $data,
  );
}

/**
 * Check number of jobs running longer than usual.
 * 
 * @return array
 * 
 * @todo Implement the logic
 */
function ultimate_cron_longrunning_check() {
  $longrunning = 0;
  
  // Get running jobs
  
  // Find out how long they have been running
  
  // Calculate average run time per job (over a threshold? E.g. queues run very fast if there is nothing to process)
  
  // If 
  
  $threshold = variable_get('ultimate_cron_nagios_longrunning_threshold', 0);
  if ($longrunning > $threshold) {
    $data = array(
      'status' => NAGIOS_STATUS_CRITICAL,
      'type'   => 'state',
      'text'   => t('@jobs jobs are running longer than usual - it is more than @threshold', array('@jobs' => $longrunning, '@threshold' => $threshold)),
    );
  }
  else {
    $data = array(
      'status' => NAGIOS_STATUS_OK, 
      'type'   => 'state',
      'text'   => t('@jobs jobs are running longer than usual', array('@jobs' => $longrunning)),
    );
  }

  return array(
    'key' => 'ULTIMATE_CRON_LONGRUNNING',
    'data' => $data,
  );
}
