<?php

namespace Drupal\update_helper;

use Psr\Log\AbstractLogger;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Output\StreamOutput;

/**
 * Helper service for logging in update hooks provided by update helper.
 *
 * It provides output of logs to HTML, when update is executed over update.php.
 * And it also provides output of logs for Drush command, when update is
 * executed over drush command.
 *
 * @package Drupal\update_helper
 */
class UpdateLogger extends AbstractLogger {

  /**
   * Container for logs.
   *
   * @var array
   */
  protected $logs = [];

  /**
   * The console logger.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $cliLogger;

  /**
   * The console output.
   *
   * @var string
   */
  protected $cliOutput = 'php://stderr';

  /**
   * {@inheritdoc}
   */
  public function log($level, $message, array $context = []) {
    $this->logs[] = [$level, $message, $context];
  }

  /**
   * Clear logs and returns currenlty collected logs.
   *
   * @return array
   *   Returns collected logs, since last clear.
   */
  protected function cleanLogs() {
    $logs = $this->logs;
    $this->logs = [];

    return $logs;
  }

  /**
   * Output logs in format suitable for HTML and clear logs too.
   *
   * @return string
   *   Returns HTML.
   */
  protected function outputHtml() {
    $full_log = '';

    $current_logs = $this->cleanLogs();
    foreach ($current_logs as $log_entry) {
      $full_log .= $log_entry[1] . '<br /><br />';
    }

    return $full_log;
  }

  /**
   * Returns console logger.
   *
   * @return \Psr\Log\LoggerInterface
   *   Returns console logger.
   */
  protected function getCliLogger() {
    if (empty($this->cliLogger)) {
      $this->cliLogger = new ConsoleLogger(new StreamOutput(fopen($this->cliOutput, 'w'), OutputInterface::VERBOSITY_DEBUG));
    }

    return $this->cliLogger;
  }

  /**
   * Output logs in format suitable for console command and clear logs too.
   */
  protected function outputCli() {
    $console_logger = $this->getCliLogger();

    foreach ($this->cleanLogs() as $log_entry) {
      $console_logger->log($log_entry[0], $log_entry[1]);
    }
  }

  /**
   * Output log result, depending on channel used and clean log.
   *
   * @return string
   *   Returns HTML string in case of non console execution.
   */
  public function output() {
    if (PHP_SAPI === 'cli') {
      $this->outputCli();

      return '';
    }

    return $this->outputHtml();
  }

}
