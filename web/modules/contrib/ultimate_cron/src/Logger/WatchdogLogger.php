<?php

namespace Drupal\ultimate_cron\Logger;

use Drupal\Core\Logger\LogMessageParserInterface;
use Drupal\Core\Logger\RfcLoggerTrait;
use Drupal\Core\Logger\RfcLogLevel;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Psr\Log\LoggerInterface as PsrLoggerInterface;

/**
 * Logs events in currently running cronjobs.
 */
class WatchdogLogger implements PsrLoggerInterface {
  use RfcLoggerTrait;
  use StringTranslationTrait;

  /**
   * Log entries for currently running cron jobs.
   *
   * @var \Drupal\ultimate_cron\Logger\LogEntry[]
   */
  protected $logEntries = [];

  /**
   * The message's placeholders parser.
   *
   * @var \Drupal\Core\Logger\LogMessageParserInterface
   */
  protected $parser;

  /**
   * Whether the shutdown handler is registered or not.
   *
   * @var bool
   */
  protected $shutdownRegistered = FALSE;

  /**
   * Constructs a WatchdogLogger object.
   *
   * @param \Drupal\Core\Logger\LogMessageParserInterface $parser
   *   The parser to use when extracting message variables.
   */
  public function __construct(LogMessageParserInterface $parser) {
    $this->parser = $parser;
  }

  /**
   * {@inheritdoc}
   */
  public function log($level, $message, array $context = array()): void {

    if ($this->logEntries) {

      // Remove any backtraces since they may contain an unserializable variable.
      unset($context['backtrace']);

      // Convert PSR3-style messages to
      // \Drupal\Component\Render\FormattableMarkup style, so they can be
      // translated too in runtime.
      $message_placeholders = $this->parser->parseMessagePlaceholders($message, $context);

      foreach ($this->logEntries as $log_entry) {
        $log_entry->log($message, $message_placeholders, $level);
      }
    }
  }

  /**
   * Begin capturing messages.
   *
   * @param LogEntry $log_entry
   *   The log entry that should capture messages.
   */
  public function catchMessages(LogEntry $log_entry) {
    // Since we may already be inside a drupal_register_shutdown_function()
    // we cannot use that. Use PHPs register_shutdown_function() instead.
    if (!$this->shutdownRegistered) {
      ultimate_cron_register_shutdown_function(
        array(
          $this,
          'catchMessagesShutdownWrapper'
        ), 'catch_messages'
      );
      $this->shutdownRegistered = TRUE;
    }

    $this->logEntries[$log_entry->lid] = $log_entry;
  }

  /**
   * End message capturing.
   *
   * Effectively disables the shutdown function for the given log entry.
   *
   * @param \Drupal\ultimate_cron\Logger\LogEntry $log_entry
   *   The log entry.
   */
  public function unCatchMessages(LogEntry $log_entry) {
    unset($this->logEntries[$log_entry->lid]);
  }

  /**
   * Shutdown handler wrapper for catching messages.
   */
  public function catchMessagesShutdownWrapper() {
    foreach ($this->logEntries as $log_entry) {
      $this->catchMessagesShutdown($log_entry);
    }
  }

  /**
   * Shutdown function callback for a single log entry.
   *
   * Ensures that a log entry has been closed properly on shutdown.
   *
   * @param LogEntry $log_entry
   *   The log entry to close.
   */
  public function catchMessagesShutdown(LogEntry $log_entry) {
    $this->unCatchMessages($log_entry);

    if ($log_entry->finished) {
      return;
    }

    // Get error messages.
    $error = error_get_last();
    if ($error) {
      $message = $error['message'] . ' (line ' . $error['line'] . ' of ' . $error['file'] . ').' . "\n";
      $severity = RfcLogLevel::INFO;
      if ($error['type'] && (E_NOTICE || E_USER_NOTICE || E_USER_WARNING)) {
        $severity = RfcLogLevel::NOTICE;
      }
      if ($error['type'] && (E_WARNING || E_CORE_WARNING || E_USER_WARNING)) {
        $severity = RfcLogLevel::WARNING;
      }
      if ($error['type'] && (E_ERROR || E_CORE_ERROR || E_USER_ERROR || E_RECOVERABLE_ERROR)) {
        $severity = RfcLogLevel::ERROR;
      }

      $log_entry->log($message, NULL, $severity);
    }
    $log_entry->finish();
  }

}
