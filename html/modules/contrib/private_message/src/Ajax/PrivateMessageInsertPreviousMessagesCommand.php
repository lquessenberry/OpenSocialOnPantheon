<?php

namespace Drupal\private_message\Ajax;

/**
 * Class to insert older private messages into a private message thread.
 */
class PrivateMessageInsertPreviousMessagesCommand extends PrivateMessageInsertMessagesCommand {

  /**
   * Constructs a PrivateMessageInsertPreviousMessagesCommand object.
   *
   * @param string $messages
   *   The HTML for the messages to be inserted in the page.
   */
  public function __construct($messages) {
    parent::__construct('previous', $messages);
  }

}
