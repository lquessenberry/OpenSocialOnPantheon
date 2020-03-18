<?php

namespace Drupal\private_message\Service;

use Drupal\Core\Session\AccountInterface;
use Drupal\private_message\Entity\PrivateMessageInterface;
use Drupal\private_message\Entity\PrivateMessageThreadInterface;

/**
 * The Private Message generator class.
 *
 * @package Drupal\private_message\Service
 */
class PrivateMessageThreadManager implements PrivateMessageThreadManagerInterface {

  /**
   * The private message service.
   *
   * @var \Drupal\private_message\Service\PrivateMessageServiceInterface
   */
  private $privateMessageService;

  /**
   * The private message mailer service.
   *
   * @var \Drupal\private_message\Service\PrivateMessageMailerInterface
   */
  private $privateMessageMailer;

  /**
   * The private message.
   *
   * @var \Drupal\private_message\Entity\PrivateMessageInterface
   */
  private $message;

  /**
   * The message recipients.
   *
   * @var \Drupal\Core\Session\AccountInterface[]
   */
  private $recipients = [];

  /**
   * An array of members to exclude from notification emails.
   *
   * @var \Drupal\Core\Session\AccountInterface[]
   */
  private $excludeFromMail = [];

  /**
   * The private message thread.
   *
   * @var \Drupal\private_message\Entity\PrivateMessageThreadInterface|null
   */
  private $thread;

  /**
   * PrivateMessageThreadManager constructor.
   *
   * @param \Drupal\private_message\Service\PrivateMessageServiceInterface $private_message_service
   *   The private message service.
   * @param \Drupal\private_message\Service\PrivateMessageMailerInterface $mailer
   *   The private message mailer service.
   */
  public function __construct(
    PrivateMessageServiceInterface $private_message_service,
    PrivateMessageMailerInterface $mailer
  ) {
    $this->privateMessageService = $private_message_service;
    $this->privateMessageMailer = $mailer;
  }

  /**
   * {@inheritdoc}
   */
  public function saveThread(PrivateMessageInterface $message, array $recipients = [], array $excludeFromMail = [], PrivateMessageThreadInterface $thread = NULL) {
    $this->message = $message;
    $this->thread = $thread;
    $this->recipients = $recipients;
    $this->excludeFromMail = $excludeFromMail;

    $this->getThread()
      ->addMessage()
      ->sendMail();
  }

  /**
   * If no thread is defined, load one from the thread members.
   *
   * @return $this
   */
  private function getThread() {
    if (is_null($this->thread)) {
      $this->thread = $this->privateMessageService->getThreadForMembers($this->recipients);
    }

    return $this;
  }

  /**
   * Add the new message to the thread.
   *
   * @return $this
   */
  private function addMessage() {
    $this->thread->addMessage($this->message);
    $this->thread->save();

    return $this;
  }

  /**
   * Send the notification email.
   *
   * @return $this
   */
  private function sendMail() {
    $this->privateMessageMailer->send($this->message, $this->thread, $this->getMailRecipients());

    return $this;
  }

  /**
   * The users to receive email notifications.
   *
   * @return \Drupal\Core\Session\AccountInterface[]
   *   An array of  Account objects of the thread memebers who are to receive
   *   the email notification.
   */
  private function getMailRecipients() {
    if (empty($this->excludeFromMail)) {
      return $this->recipients;
    }

    return array_filter($this->recipients, function (AccountInterface $account) {
      // If this user is in the excluded list, filter them from the recipients
      // list so they do not receive the email.
      return !in_array($account, $this->excludeFromMail);
    });
  }

}
