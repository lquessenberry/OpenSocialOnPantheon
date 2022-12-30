<?php

namespace Drupal\ultimate_cron\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Logger\RfcLogLevel;
use Drupal\Core\Session\AnonymousUserSession;
use Drupal\Core\Utility\Error;
use Drupal\ultimate_cron\CronJobInterface;

/**
 * Class for handling cron jobs.
 *
 * This class represents the jobs available in the system.
 *
 * @ConfigEntityType(
 *   id = "ultimate_cron_job",
 *   label = @Translation("Cron Job"),
 *   handlers = {
 *     "access" = "Drupal\ultimate_cron\CronJobAccessControlHandler",
 *     "list_builder" = "Drupal\ultimate_cron\CronJobListBuilder",
 *     "form" = {
 *       "default" = "Drupal\ultimate_cron\Form\CronJobForm",
 *       "delete" = "\Drupal\Core\Entity\EntityDeleteForm",
 *       "disable" = "Drupal\ultimate_cron\Form\CronJobDisableForm",
 *       "enable" = "Drupal\ultimate_cron\Form\CronJobEnableForm",
 *     }
 *   },
 *   config_prefix = "job",
 *   admin_permission = "administer ultimate cron",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "title",
 *     "status" = "status",
 *     "weight" = "weight",
 *   },
 *   config_export = {
 *     "title",
 *     "id",
 *     "status",
 *     "weight",
 *     "module",
 *     "callback",
 *     "scheduler",
 *     "launcher",
 *     "logger",
 *   },
 *   links = {
 *     "edit-form" = "/admin/config/system/cron/jobs/manage/{ultimate_cron_job}",
 *     "delete-form" = "/admin/config/system/cron/jobs/manage/{ultimate_cron_job}/delete",
 *     "collection" = "/admin/config/system/cron/jobs",
 *     "run" = "/admin/config/system/cron/jobs/{ultimate_cron_job}/run",
 *     "disable" = "/admin/config/system/cron/jobs/manage/{ultimate_cron_job}/disable",
 *     "enable" = "/admin/config/system/cron/jobs/manage/{ultimate_cron_job}/enable",
 *     "logs" = "/admin/config/system/cron/jobs/logs/{ultimate_cron_job}",
 *     "unlock" = "/admin/config/system/cron/jobs/{ultimate_cron_job}/unlock",
 *   }
 * )
 */
class CronJob extends ConfigEntityBase implements CronJobInterface {
  static public $signals;
  static public $currentJob;
  public $progressUpdated = 0;
  public $settings;

  /**
   * @var int
   */
  protected $id;

  /**
   * @var int
   */
  protected $uuid;

  /**
   * @var bool
   */
  protected $status = TRUE;

  /**
   * The weight.
   *
   * @var int
   */
  protected $weight = 0;

  /**
   * @var string
   */
  protected $title;

  /**
   * @var string
   */
  protected $callback;

  /**
   * @var string
   */
  protected $module;

  /**
   * @var array
   */
  protected $scheduler = array('id' => 'simple');

  /**
   * @var array
   */
  protected $launcher = array('id' => 'serial');

  /**
   * @var array
   */
  protected $logger = array('id' => 'database');

  /**
   * @var \Drupal\ultimate_cron\CronPlugin
   */
  protected $plugins = [];

  /**
   * The class resolver.
   *
   * @var \Drupal\Core\DependencyInjection\ClassResolverInterface
   */
  protected $classResolver;

  /**
   * The module extension list service.
   *
   * @var \Drupal\Core\Extension\ModuleExtensionList
   */
  protected $moduleExtensionList;

  /**
   * CronJob constructor.
   *
   * @param array $values
   * @param string $entity_type
   */
  public function __construct(array $values, $entity_type) {
    parent::__construct($values, $entity_type);
    $this->classResolver = \Drupal::service('class_resolver');
    $this->moduleExtensionList = \Drupal::service('extension.list.module');

  }

  /**
   * {@inheritdoc}
   */
  public function postSave(EntityStorageInterface $storage, $update = TRUE) {
    parent::postSave($storage, $update);
    if ($update && empty($this->dont_log)) {
      $log = $this->startLog(uniqid($this->id(), TRUE), '', ULTIMATE_CRON_LOG_TYPE_ADMIN);
      $log->log('Job modified by ' . $log->formatUser(), array(), RfcLogLevel::INFO);
      $log->finish();
    }
  }

  /**
   * Set configuration for a given plugin type.
   *
   * @param string $plugin_type
   *   launcher, logger or scheduler.
   * @param array $configuration
   *   The configuration array.
   */
  public function setConfiguration($plugin_type, array $configuration) {
    $this->{$plugin_type}['configuration'] = $configuration;
  }

  /**
   * {@inheritdoc}
   */
  public static function postDelete(EntityStorageInterface $storage, array $entities) {
    foreach ($entities as $entity) {
      if (empty($entity->dont_log)) {
        /** @var \Drupal\ultimate_cron\Entity\CronJob $entity */
        $log = $entity->startLog(uniqid($entity->id(), TRUE), 'modification', ULTIMATE_CRON_LOG_TYPE_ADMIN);
        $log->log('Job deleted by ' . $log->formatUser(), array(), RfcLogLevel::INFO);
        $log->finish();
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function isValid() {
    return is_callable($this->getCallback());
  }

  /**
   * Get a signal without affecting it.
   *
   * @see UltimateCronSignal::peek()
   */
  public function peekSignal($signal) {
    if (isset(self::$signals[$this->id()][$signal])) {
      return TRUE;
    }
    $signal = \Drupal::service('ultimate_cron.signal');;
    return $signal->peek($this->id(), $signal);
  }

  /**
   * Get a signal and clear it if found.
   *
   * @see UltimateCronSignal::get()
   */
  public function getSignal($signal) {
    if (isset(self::$signals[$this->id()][$signal])) {
      unset(self::$signals[$this->id()][$signal]);
      return TRUE;
    }
    $service = \Drupal::service('ultimate_cron.signal');;
    return $service->get($this->id(), $signal);
  }

  /**
   * Send a signal.
   *
   * @see UltimateCronSignal::set()
   */
  public function sendSignal($signal, $persist = FALSE) {
    if ($persist) {
      $signal = \Drupal::service('ultimate_cron.signal');;
      $signal->set($this->id(), $signal);
    }
    else {
      self::$signals[$this->id()][$signal] = TRUE;
    }
  }

  /**
   * Clear a signal.
   *
   * @see UltimateCronSignal::clear()
   */
  public function clearSignal($signal) {
    unset(self::$signals[$this->id()][$signal]);
    $signal = \Drupal::service('ultimate_cron.signal');;
    $signal->clear($this->id(), $signal);
  }

  /**
   * Send all signal for the job.
   *
   * @see UltimateCronSignal::flush()
   */
  public function clearSignals() {
    unset(self::$signals[$this->id()]);
    $signal = \Drupal::service('ultimate_cron.signal');;
    $signal->flush($this->id());
  }

  /**
   * Get job plugin.
   *
   * If no plugin name is provided current plugin of the specified type will
   * be returned.
   *
   * @param string $plugin_type
   *   Name of plugin type.
   * @param string $name
   *   (optional) The name of the plugin.
   *
   * @return mixed
   *   Plugin instance of the specified type.
   */
  public function getPlugin($plugin_type, $name = NULL) {
    if ($name) {
      return ultimate_cron_plugin_load($plugin_type, $name);
    }
    // @todo: enable static cache, needs unset when values change.
    //    if (isset($this->plugins[$plugin_type])) {
    //      return $this->plugins[$plugin_type];
    //    }
    if ($name) {
    }
    elseif (!empty($this->{$plugin_type}['id'])) {
      $name = $this->{$plugin_type}['id'];
    }
    else {
      $name = $this->hook[$plugin_type]['name'];
    }
    /* @var \Drupal\Core\Plugin\DefaultPluginManager $manager */
    $manager = \Drupal::service('plugin.manager.ultimate_cron.' . $plugin_type);
    $this->plugins[$plugin_type] = $manager->createInstance($name, isset($this->{$plugin_type}['configuration']) ? $this->{$plugin_type}['configuration'] : array());
    return $this->plugins[$plugin_type];
  }

  /**
   * Gets this plugin's configuration.
   *
   * @param $plugin_type
   *   The type of plugin.
   * @return array
   *   An array of this plugin's configuration.
   */
  public function getConfiguration($plugin_type) {
    if (!isset($this->{$plugin_type}['configuration'])) {
      $this->{$plugin_type}['configuration'] = $this->getPlugin($plugin_type)->defaultConfiguration();
    }

    return $this->{$plugin_type}['configuration'];
  }

  /**
   * Signal page for plugins.
   */
  public function signal($item, $plugin_type, $plugin_name, $signal) {
    $plugin = ultimate_cron_plugin_load($plugin_type, $plugin_name);
    return $plugin->signal($item, $signal);
  }

  /**
   * Invokes the jobs callback.
   */
  protected function invokeCallback() {
    $callback = $this->getCallback();
    return call_user_func($callback, $this);
  }

  /**
   * Returns a callable for the given controller.
   *
   * @param string $callback
   *   A callback string.
   *
   * @return mixed
   *   A PHP callable.
   *
   * @throws \InvalidArgumentException
   *   If the callback class does not exist.
   */
  protected function resolveCallback($callback) {
    // Controller in the service:method notation.
    $count = substr_count($callback, ':');
    if ($count == 1) {
      list($class_or_service, $method) = explode(':', $callback, 2);
    }
    // Controller in the class::method notation.
    elseif (strpos($callback, '::') !== FALSE) {
      list($class_or_service, $method) = explode('::', $callback, 2);
    }
    else {
      return $callback;
    }

    $callback = $this->classResolver->getInstanceFromDefinition($class_or_service);

    return array($callback, $method);
  }

  /**
   * Check job schedule.
   */
  public function isScheduled() {
    \Drupal::moduleHandler()->invokeAll('cron_pre_schedule', array($this));
    $result = $this->status() && !$this->isLocked() && $this->getPlugin('scheduler')
        ->isScheduled($this);
    \Drupal::moduleHandler()->invokeAll('cron_post_schedule', array($this));
    return $result;
  }

  /**
   * Check if job is behind its schedule.
   *
   * @return bool|int
   *   FALSE if job is behind its schedule or number of seconds behind.
   */
  public function isBehindSchedule() {
    return $this->getPlugin('scheduler')->isBehind($this);
  }

  /**
   * Lock job.
   */
  public function lock() {
    $launcher = $this->getPlugin('launcher');
    $lock_id = $launcher->lock($this);
    if (!$lock_id) {
      \Drupal::logger('ultimate_cron')->error('Could not get lock for job @name', array(
        '@name' => $this->id(),
      ));
      return FALSE;
    }
    $this->sendMessage('lock', array(
      'lock_id' => $lock_id,
    ));
    return $lock_id;
  }

  /**
   * Unlock job.
   *
   * @param string $lock_id
   *   The lock id to unlock.
   * @param bool $manual
   *   Whether or not this is a manual unlock.
   */
  public function unlock($lock_id = NULL, $manual = FALSE) {
    $result = NULL;
    if (!$lock_id) {
      $lock_id = $this->isLocked();
    }
    if ($lock_id) {
      $result = $this->getPlugin('launcher')->unlock($lock_id, $manual);
    }
    $this->sendMessage('unlock', array(
      'lock_id' => $lock_id,
    ));
    return $result;
  }

  /**
   * Get locked state of job.
   */
  public function isLocked() {
    return $this->getPlugin('launcher')->isLocked($this);
  }

  /**
   * Get locked state for multiple jobs.
   *
   * @param array $jobs
   *   Jobs to check locks for.
   */
  static public function isLockedMultiple($jobs) {
    $launchers = array();
    foreach ($jobs as $job) {
      $launchers[$job->getPlugin('launcher')->name][$job->id()] = $job;
    }
    $locked = array();
    foreach ($launchers as $launcher => $jobs) {
      $locked += ultimate_cron_plugin_load('launcher', $launcher)->isLockedMultiple($jobs);
    }
    return $locked;
  }

  /**
   * {@inheritdoc}
   */
  public function run($init_message = NULL) {
    if (!$init_message) {
      $init_message = t('Launched manually');
    }

    $lock_id = $this->lock();
    if (!$lock_id) {
      return FALSE;
    }
    $log_entry = $this->startLog($lock_id, $init_message);

    $accountSwitcher = \Drupal::service('account_switcher');

    try {
      $this->clearSignals();
      $this->initializeProgress();
      \Drupal::moduleHandler()->invokeAll('cron_pre_run', array($this));

      // Force the current user to anonymous to ensure consistent permissions
      // on cron runs.
      $accountSwitcher->switchTo(new AnonymousUserSession());

      self::$currentJob = $this;
      $this->invokeCallback();
    }
    catch (\Error $e) {
      // PHP 7 throws Error objects in case of a fatal error. It will also call
      // the finally block below and close the log entry. Because of that,
      // the global fatal error catching will not work and we have to log it
      // explicitly here instead. The advantage is that this will not
      // interrupt the process.
      $variables = Error::decodeException($e);
      $variables = array_filter($variables, function ($key) {
        return $key[0] == '@' || $key[0] == '%';
      }, ARRAY_FILTER_USE_KEY);
      $log_entry->log('%type: @message in %function (line %line of %file).', $variables, RfcLogLevel::ERROR);
      return FALSE;
    }
    catch (\Exception $e) {
      $variables = Error::decodeException($e);
      $variables = array_filter($variables, function ($key) {
        return $key[0] == '@' || $key[0] == '%';
      }, ARRAY_FILTER_USE_KEY);
      $log_entry->log('%type: @message in %function (line %line of %file).', $variables, RfcLogLevel::ERROR);
      return FALSE;
    }
    finally {
      self::$currentJob = NULL;
      \Drupal::moduleHandler()->invokeAll('cron_post_run', array($this));
      $this->finishProgress();

      // Restore original user account.
      $accountSwitcher->switchBack();
      $log_entry->finish();
      $this->unlock($lock_id);
    }
    return TRUE;
  }

  /**
   * Get log entries.
   *
   * @param int $limit
   *   (optional) Number of log entries per page.
   *
   * @return \Drupal\ultimate_cron\Logger\LogEntry[]
   *   Array of UltimateCronLogEntry objects.
   */
  public function getLogEntries($log_types = ULTIMATE_CRON_LOG_TYPE_ALL, $limit = 10) {
    $log_types = $log_types == ULTIMATE_CRON_LOG_TYPE_ALL ? _ultimate_cron_define_log_type_all() : $log_types;
    return $this->getPlugin('logger')
      ->getLogEntries($this->id(), $log_types, $limit);
  }

  /**
   * Load log entry.
   *
   * @param string $lock_id
   *   The lock id of the log entry.
   *
   * @return LogEntry
   *   The log entry.
   */
  public function loadLogEntry($lock_id) {
    return $this->getPlugin('logger')->load($this->id(), $lock_id);
  }

  /**
   * Load latest log.
   *
   * @return LogEntry
   *   The latest log entry for this job.
   */
  public function loadLatestLogEntry($log_types = array(ULTIMATE_CRON_LOG_TYPE_NORMAL)) {
    return $this->getPlugin('logger')->load($this->id(), NULL, $log_types);
  }

  /**
   * Load latest log entries.
   *
   * @param array $jobs
   *   Jobs to load log entries for.
   *
   * @return array
   *   Array of UltimateCronLogEntry objects.
   */
  static public function loadLatestLogEntries($jobs, $log_types = array(ULTIMATE_CRON_LOG_TYPE_NORMAL)) {
    $loggers = array();
    foreach ($jobs as $job) {
      $loggers[$job->getPlugin('logger')->name][$job->id()] = $job;
    }
    $log_entries = array();
    foreach ($loggers as $logger => $jobs) {
      $log_entries += ultimate_cron_plugin_load('logger', $logger)->loadLatestLogEntries($jobs, $log_types);
    }
    return $log_entries;
  }

  /**
   * {@inheritdoc}
   */
  public function startLog($lock_id, $init_message = '', $log_type = ULTIMATE_CRON_LOG_TYPE_NORMAL) {
    $logger = $this->getPlugin('logger');
    $log_entry = $logger->createEntry($this->id(), $lock_id, $init_message, $log_type);
    \Drupal::service('logger.ultimate_cron')->catchMessages($log_entry);
    return $log_entry;
  }

  /**
   * Resume a previosly saved log.
   *
   * @param string $lock_id
   *   The lock id of the log to resume.
   *
   * @return LogEntry
   *   The log entry object.
   */
  public function resumeLog($lock_id) {
    $logger = $this->getPlugin('logger');
    $log_entry = $logger->load($this->id(), $lock_id);
    $log_entry->finished = FALSE;
    \Drupal::service('logger.ultimate_cron')->catchMessages($log_entry);
    return $log_entry;
  }

  /**
   * Get module name for this job.
   */
  public function getModuleName() {
    static $names = array();
    if (!isset($names[$this->module])) {
      $info = $this->moduleExtensionList->getExtensionInfo($this->module);
      $names[$this->module] = $info && !empty($info['name']) ? $info['name'] : $this->module;
    }
    return $names[$this->module];
  }

  /**
   * Get module description for this job.
   */
  public function getModuleDescription() {
    static $descs = array();
    if (!isset($descs[$this->module])) {
      $info = $this->moduleExtensionList->getExtensionInfo($this->module);
      $descs[$this->module] = $info && !empty($info['description']) ? $info['description'] : '';
    }
    return $descs[$this->module];
  }

  /**
   * Initialize progress.
   */
  public function initializeProgress() {
    return $this->getPlugin('launcher')->initializeProgress($this);
  }

  /**
   * Finish progress.
   */
  public function finishProgress() {
    return $this->getPlugin('launcher')->finishProgress($this);
  }

  /**
   * Get job progress.
   *
   * @return float
   *   The progress of this job.
   */
  public function getProgress() {
    return $this->getPlugin('launcher')->getProgress($this);
  }

  /**
   * Get multiple job progresses.
   *
   * @param array $jobs
   *   Jobs to get progress for.
   *
   * @return array
   *   Progress of jobs, keyed by job name.
   */
  static public function getProgressMultiple($jobs) {
    $launchers = array();
    foreach ($jobs as $job) {
      $launchers[$job->getPlugin('launcher')->name][$job->id()] = $job;
    }
    $progresses = array();
    foreach ($launchers as $launcher => $jobs) {
      $progresses += ultimate_cron_plugin_load('launcher', $launcher)->getProgressMultiple($jobs);
    }
    return $progresses;
  }

  /**
   * Set job progress.
   *
   * @param float $progress
   *   The progress (0 - 1).
   */
  public function setProgress($progress) {
    if ($this->getPlugin('launcher')->setProgress($this, $progress)) {
      $this->sendMessage('setProgress', array(
        'progress' => $progress,
      ));
      return TRUE;
    }
    return FALSE;
  }

  /**
   * Format progress.
   *
   * @param float $progress
   *   (optional) The progress to format. Uses the progress on the object
   *              if not specified.
   *
   * @return string
   *   Formatted progress.
   */
  public function formatProgress($progress = NULL) {
    if (!isset($progress)) {
      $progress = isset($this->progress) ? $this->progress : $this->getProgress();
    }
    return $this->getPlugin('launcher')->formatProgress($this, $progress);
  }

  /**
   * Get a "unique" id for a job.
   */
  public function getUniqueID() {
    return isset($this->ids[$this->id()]) ? $this->ids[$this->id()] : $this->ids[$this->id()] = hexdec(substr(sha1($this->id()), -8));
  }

  /**
   * Send a nodejs message.
   *
   * @param string $action
   *   The action performed.
   * @param array $data
   *   Data blob for the given action.
   */
  public function sendMessage($action, $data = array()) {
    // @TODO: Nodejs integration has not been ported to 8.x yet.
    if (FALSE && \Drupal::moduleHandler()->moduleExists('nodejs')) {
      $settings = ultimate_cron_plugin_load('settings', 'general')->getDefaultSettings();
      if (empty($settings['nodejs'])) {
        return;
      }

      $elements = array();

      $build = clone $this;

      $cell_idxs = array();

      switch ($action) {
        case 'lock':
          $logger = $build->getPlugin('logger');
          if (empty($data['log_entry'])) {
            $build->lock_id = $data['lock_id'];
            $build->log_entry = $logger->factoryLogEntry($build->name);
            $build->log_entry->setData(array(
              'lid' => $data['lock_id'],
              'start_time' => microtime(TRUE),
            ));
          }
          else {
            $build->log_entry = $data['log_entry'];
          }
          $cell_idxs = array(
            'tr#' . $build->name . ' .ctools-export-ui-start-time' => 3,
            'tr#' . $build->name . ' .ctools-export-ui-duration' => 4,
            'tr#' . $build->name . ' .ctools-export-ui-status' => 5,
            'tr#' . $build->name . ' .ctools-export-ui-operations' => 7,
          );
          break;

        case 'unlock':
          $build->log_entry = $build->loadLogEntry($data['lock_id']);
          $build->lock_id = FALSE;
          $cell_idxs = array(
            'tr#' . $build->name . ' .ctools-export-ui-start-time' => 3,
            'tr#' . $build->name . ' .ctools-export-ui-duration' => 4,
            'tr#' . $build->name . ' .ctools-export-ui-status' => 5,
            'tr#' . $build->name . ' .ctools-export-ui-operations' => 7,
          );
          break;

        case 'setProgress':
          $build->lock_id = $build->isLocked();
          $build->log_entry = $build->loadLogEntry($build->lock_id);
          $cell_idxs = array(
            'tr#' . $build->name . ' .ctools-export-ui-start-time' => 3,
            'tr#' . $build->name . ' .ctools-export-ui-duration' => 4,
            'tr#' . $build->name . ' .ctools-export-ui-status' => 5,
          );
          break;
      }
      $cells = $build->rebuild_ctools_export_ui_table_row();
      foreach ($cell_idxs as $selector => $cell_idx) {
        $elements[$selector] = $cells[$cell_idx];
      }

      $message = (object) array(
        'channel' => 'ultimate_cron',
        'data' => (object) array(
          'action' => $action,
          'job' => $build,
          'timestamp' => microtime(TRUE),
          'elements' => $elements,
        ),
        'callback' => 'nodejsUltimateCron',
      );
      nodejs_send_content_channel_message($message);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies() {
    parent::calculateDependencies();

    $this->addDependency('module', $this->getModule());

    return $this->dependencies;
  }

  /**
   * {@inheritdoc}
   */
  public function getTitle() {
    return $this->title;
  }

  /**
   * {@inheritdoc}
   */
  public function getCallback() {
    if (is_callable($this->callback)) {
      return $this->callback;
    }
    else {
      return $this->resolveCallback($this->callback);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getModule() {
    return $this->module;
  }

  /**
   * {@inheritdoc}
   */
  public function getSchedulerId() {
    return $this->scheduler;
  }

  /**
   * {@inheritdoc}
   */
  public function getLauncherId() {
    return $this->launcher['id'];
  }

  /**
   * {@inheritdoc}
   */
  public function getLoggerId() {
    return $this->logger['id'];
  }

  /**
   * {@inheritdoc}
   */
  public function setTitle($title) {
    $this->set('title', $title);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setCallback($callback) {
    $this->set('callback', $callback);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setModule($module) {
    $this->set('module', $module);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setSchedulerId($scheduler_id) {
    $this->scheduler['id'] = $scheduler_id;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setLauncherId($launcher_id) {
    $this->launcher['id'] = $launcher_id;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setLoggerId($logger_id) {
    $this->logger['id'] = $logger_id;
    return $this;
  }

}
