<?php

namespace Drupal\private_message\Ajax;

use Drupal\Core\Ajax\CommandInterface;

/**
 * Ajax Command to insert private message inbox threads.
 */
class PrivateMessageInboxInsertThreadsCommand implements CommandInterface {

  /**
   * The HTML for the threads to be inserted in the page.
   *
   * @var string
   */
  protected $threads;

  /**
   * Constructs a PrivateMessageInboxInsertThreadsCommand object.
   *
   * @param string $threads
   *   The HTML for the threads to be inserted in the page.
   */
  public function __construct($threads) {
    $this->threads = $threads;
  }

  /**
   * {@inheritdoc}
   */
  public function render() {
    return [
      'command' => 'insertInboxOldPrivateMessageThreads',
      'threads' => $this->threads,
    ];
  }

}
