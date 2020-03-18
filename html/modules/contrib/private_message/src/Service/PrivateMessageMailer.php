<?php

namespace Drupal\private_message\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Mail\MailManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\private_message\Entity\PrivateMessageInterface;
use Drupal\private_message\Entity\PrivateMessageThreadInterface;
use Drupal\user\UserDataInterface;

/**
 * A service class for sending notification emails for private messages.
 */
class PrivateMessageMailer implements PrivateMessageMailerInterface {

  /**
   * The private message service.
   *
   * @var \Drupal\private_message\Service\PrivateMessageServiceInterface
   */
  private $privateMessageService;

  /**
   * The mail manager service.
   *
   * @var \Drupal\Core\Mail\MailManagerInterface
   */
  private $mailManager;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  private $currentUser;

  /**
   * The user data service.
   *
   * @var \Drupal\user\UserDataInterface
   */
  private $userData;

  /**
   * The configuration factory service.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  private $config;

  /**
   * Constructs a new PrivateMessageMailer object.
   *
   * @param \Drupal\private_message\Service\PrivateMessageServiceInterface $privateMessageService
   *   The private message service.
   * @param \Drupal\Core\Mail\MailManagerInterface $mailManager
   *   The mail manager service.
   * @param \Drupal\Core\Session\AccountProxyInterface $currentUser
   *   The current user.
   * @param \Drupal\user\UserDataInterface $userData
   *   The user data service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The configuration factory service.
   */
  public function __construct(
    PrivateMessageServiceInterface $privateMessageService,
    MailManagerInterface $mailManager,
    AccountProxyInterface $currentUser,
    UserDataInterface $userData,
    ConfigFactoryInterface $configFactory
  ) {
    $this->privateMessageService = $privateMessageService;
    $this->mailManager = $mailManager;
    $this->currentUser = $currentUser;
    $this->userData = $userData;
    $this->config = $configFactory->get('private_message.settings');
  }

  /**
   * {@inheritdoc}
   */
  public function send(PrivateMessageInterface $message, PrivateMessageThreadInterface $thread, array $members = []) {
    $params = [
      'private_message' => $message,
      'private_message_thread' => $thread,
    ];

    foreach ($members as $member) {
      if ($member->id() != $this->currentUser->id()) {
        $params['member'] = $member;

        // Should the message be sent?
        if ($this->shouldSend($member)) {
          $this->mailManager->mail('private_message', 'message_notification', $member->getEmail(), $member->getPreferredLangcode(), $params);
        }
      }
    }
  }

  /**
   * Determines if the message should be sent.
   *
   * Checks individual user preferences as well as system defaults.
   *
   * @param \Drupal\Core\Session\AccountInterface $recipient
   *   The potential recipient.
   *
   * @return bool
   *   A boolean indicating whether or not the message should be sent.
   */
  private function shouldSend(AccountInterface $recipient) {
    $send = (bool) $this->userData->get('private_message', $recipient->id(), 'email_notification');

    return is_numeric($send)
      ? $send
      : ($this->config->get('enable_email_notifications') && $this->config->get('send_by_default'));
  }

}
