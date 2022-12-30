<?php

namespace Drupal\ultimate_cron\Logger;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Logger\RfcLogLevel;
use Drupal\user\Entity\User;

/**
 * Class for Ultimate Cron log entries.
 *
 * Important properties:
 *   $log_entry_size
 *     - The maximum number of characters of the message in the log entry.
 */
class LogEntry {
  public $lid = NULL;
  public $name = '';
  public $log_type = ULTIMATE_CRON_LOG_TYPE_NORMAL;
  public $uid = NULL;
  public $start_time = 0;
  public $end_time = 0;
  public $init_message = '';
  public $message = '';
  public $severity = -1;

  // Default 1MiB log entry.
  public $log_entry_size = 1048576;

  public $log_entry_fields = array(
    'lid',
    'uid',
    'log_type',
    'start_time',
    'end_time',
    'init_message',
    'message',
    'severity',
  );

  public $logger;
  public $job;
  public $finished = FALSE;

  /**
   * Constructor.
   *
   * @param string $name
   *   Name of log.
   * @param \Drupal\ultimate_cron\Logger\LoggerBase $logger
   *   A logger object.
   */
  public function __construct($name, $logger, $log_type = ULTIMATE_CRON_LOG_TYPE_NORMAL) {
    $this->name = $name;
    $this->logger = $logger;
    $this->log_type = $log_type;
    if (!isset($this->uid)) {
      $this->uid = \Drupal::currentUser()->id();
    }
  }

  /**
   * Get current log entry data as an associative array.
   *
   * @return array
   *   Log entry data.
   */
  public function getData() {
    $result = array();
    foreach ($this->log_entry_fields as $field) {
      $result[$field] = $this->$field;
    }
    return $result;
  }

  /**
   * Set current log entry data from an associative array.
   *
   * @param array $data
   *   Log entry data.
   */
  public function setData($data) {
    foreach ($this->log_entry_fields as $field) {
      if (array_key_exists($field, $data)) {
        $this->$field = $data[$field];
      }
    }
  }

  /**
   * Finish a log and save it if applicable.
   */
  public function finish() {
    if (!$this->finished) {
      \Drupal::service('logger.ultimate_cron')->unCatchMessages($this);
      $this->end_time = microtime(TRUE);
      $this->finished = TRUE;
      $this->save();
    }
  }

  /**
   * Logs a message.
   *
   * @param string $message
   *   The message to log.
   * @param array $variables
   *   Replacement variables for t().
   * @param int $level
   *   The log level, see \Drupal\Core\Logger\RfcLogLevel.
   */
  public function log($message, $variables = array(), $level = RfcLogLevel::NOTICE) {

    if ($variables !== NULL && gettype($message) === 'string') {
      $message = t($message, $variables);
    }

    $this->message .= $message;
    if ($this->severity < 0 || $this->severity > $level) {
      $this->severity = $level;
    }
    // Make sure that message doesn't become too big.
    if (mb_strlen($this->message) > $this->log_entry_size) {
      while (mb_strlen($this->message) > $this->log_entry_size) {
        $firstline = mb_strpos(rtrim($this->message, "\n"), "\n");
        if ($firstline === FALSE || $firstline == mb_strlen($this->message)) {
          // Only one line? That's a big line ... truncate it without mercy!
          $this->message = mb_substr($this->message, -$this->log_entry_size);
          break;
        }
        $this->message = mb_substr($this->message, $firstline + 1);
      }
      $this->message = '.....' . $this->message;
    }
  }

  /**
   * Get duration.
   */
  public function getDuration() {
    $duration = 0;
    if ($this->start_time && $this->end_time) {
      $duration = (int) ($this->end_time - $this->start_time);
    }
    elseif ($this->start_time) {
      $duration = (int) (microtime(TRUE) - $this->start_time);
    }
    return $duration;
  }

  /**
   * Format duration.
   */
  public function formatDuration() {
    $duration = $this->getDuration();
    switch (TRUE) {
      case $duration >= 86400:
        $format = 'd H:i:s';
        break;

      case $duration >= 3600:
        $format = 'H:i:s';
        break;

      default:
        $format = 'i:s';
    }
    return isset($duration) ? gmdate($format, $duration) : t('N/A');
  }

  /**
   * Format start time.
   */
  public function formatStartTime() {
    return $this->start_time ? \Drupal::service('date.formatter')->format((int) $this->start_time, 'custom', 'Y-m-d H:i:s') : t('Never');
  }

  /**
   * Format end time.
   */
  public function formatEndTime() {
    return $this->end_time ? \Drupal::service('date.formatter')->format((int) $this->end_time, 'custom', 'Y-m-d H:i:s') : '';
  }

  /**
   * Format user.
   */
  public function formatUser() {
    $username = t('anonymous') . ' (0)';
    if ($this->uid) {
      $user = User::load($this->uid);
      $username = $user ? new FormattableMarkup('@username (@uid)', array('@username' => $user->getDisplayName(), '@uid' => $user->id())) : t('N/A');
    }
    return $username;
  }

  /**
   * Format initial message.
   */
  public function formatInitMessage() {
    if ($this->start_time) {
      return $this->init_message ? $this->init_message . ' ' . t('by') . ' ' . $this->formatUser() : t('N/A');
    }
    else {
      $registered = variable_get('ultimate_cron_hooks_registered', array());
      return !empty($registered[$this->name]) ? t('Registered at @datetime', array(
        '@datetime' => \Drupal::service('date.formatter')->format($registered[$this->name], 'custom', 'Y-m-d H:i:s'),
      )) : t('N/A');
    }
  }

  /**
   * Format severity.
   */
  public function formatSeverity() {
    switch ($this->severity) {
      case RfcLogLevel::EMERGENCY:
      case RfcLogLevel::ALERT:
      case RfcLogLevel::CRITICAL:
      case RfcLogLevel::ERROR:
        $file = 'core/misc/icons/e32700/error.svg';
        break;

      case RfcLogLevel::WARNING:
        $file = 'core/misc/icons/e29700/warning.svg';
        break;

      case RfcLogLevel::NOTICE:
        // @todo Look for a better icon.
        $file = 'core/misc/icons/008ee6/twistie-up.svg';
        break;

      case RfcLogLevel::INFO:
      case RfcLogLevel::DEBUG:
      default:
        $file = 'core/misc/icons/73b355/check.svg';
    }
    $status = ['#theme' => 'image', '#uri' => $file];
    $severity_levels = array(
        -1 => t('no info'),
      ) + RfcLogLevel::getLevels();
    $title = $severity_levels[$this->severity];
    return array($status, $title);
  }

  /**
   * Save log entry.
   */
  public function save() {
    $this->logger->save($this);
  }
}
