<?php

namespace Drupal\private_message\Service;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\private_message\Entity\PrivateMessageInterface;
use Drupal\private_message\Mapper\PrivateMessageMapperInterface;
use Drupal\user\UserDataInterface;
use Drupal\user\UserInterface;

/**
 * The Private Message service for the private message module.
 */
class PrivateMessageService implements PrivateMessageServiceInterface {

  /**
   * The private message mapper service.
   *
   * @var \Drupal\private_message\Mapper\PrivateMessageMapperInterface
   */
  protected $mapper;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * The configuration factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The user data service.
   *
   * @var \Drupal\user\UserDataInterface
   */
  protected $userData;

  /**
   * Cache Tags Invalidator.
   *
   * @var \Drupal\Core\Cache\CacheTagsInvalidatorInterface
   */
  protected $cacheTagsInvalidator;

  /**
   * The private message thread manager.
   *
   * @var \Drupal\Core\Entity\Sql\SqlContentEntityStorage
   */
  protected $pmThreadManager;

  /**
   * The user entity manager.
   *
   * @var \Crupal\user\UserStorageInterface
   */
  protected $userManager;

  /**
   * The time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * Constructs a PrivateMessageService object.
   *
   * @param Drupal\private_message\Mapper\PrivateMessageMapperInterface $mapper
   *   The private message mapper service.
   * @param \Drupal\Core\Session\AccountProxyInterface $currentUser
   *   The current user.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The configuration factory.
   * @param \Drupal\user\UserDataInterface $userData
   *   The user data service.
   * @param \Drupal\Core\Cache\CacheTagsInvalidatorInterface $cacheTagsInvalidator
   *   The cache tags invalidator interface.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager interface.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   */
  public function __construct(
    PrivateMessageMapperInterface $mapper,
    AccountProxyInterface $currentUser,
    ConfigFactoryInterface $configFactory,
    UserDataInterface $userData,
    CacheTagsInvalidatorInterface $cacheTagsInvalidator,
    EntityTypeManagerInterface $entityTypeManager,
    TimeInterface $time
  ) {
    $this->mapper = $mapper;
    $this->currentUser = $currentUser;
    $this->configFactory = $configFactory;
    $this->userData = $userData;
    $this->cacheTagsInvalidator = $cacheTagsInvalidator;
    $this->pmThreadManager = $entityTypeManager->getStorage('private_message_thread');
    $this->userManager = $entityTypeManager->getStorage('user');
    $this->time = $time;
  }

  /**
   * {@inheritdoc}
   */
  public function getThreadForMembers(array $members) {
    $thread_id = $this->mapper->getThreadIdForMembers($members);

    if ($thread_id) {
      return $this->pmThreadManager->load($thread_id);
    }
    else {
      return $this->createPrivateMessageThread($members);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getFirstThreadForUser(UserInterface $user) {
    $thread_id = $this->mapper->getFirstThreadIdForUser($user);
    if ($thread_id) {
      return $this->pmThreadManager->load($thread_id);
    }

    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function getThreadsForUser($count, $timestamp = FALSE) {
    $return = [
      'threads' => [],
      'next_exists' => FALSE,
    ];

    $user = $this->userManager->load($this->currentUser->id());
    $thread_ids = $this->mapper->getThreadIdsForUser($user, $count, $timestamp);
    if (count($thread_ids)) {
      $threads = $this->pmThreadManager->loadMultiple($thread_ids);
      if (count($threads)) {
        $last_thread = end($threads);
        $last_timestamp = $last_thread->get('updated')->value;
        $return['next_exists'] = $this->mapper->checkForNextThread($user, $last_timestamp);
        $return['threads'] = $threads;
      }
    }

    return $return;
  }

  /**
   * {@inheritdoc}
   */
  public function getNewMessages($threadId, $messageId) {
    $response = [];

    $private_message_thread = $this->pmThreadManager->load($threadId);
    if ($private_message_thread && $private_message_thread->isMember($this->currentUser->id())) {
      $messages = $private_message_thread->getMessages();
      $from_index = FALSE;
      foreach ($messages as $index => $message) {
        if ($message->id() > $messageId) {
          $from_index = $index;
          break;
        }
      }

      if ($from_index !== FALSE) {
        $response = array_splice($messages, $from_index);
      }
    }

    return $response;
  }

  /**
   * {@inheritdoc}
   */
  public function getPreviousMessages($threadId, $messageId) {
    $return = [];

    $private_message_thread = $this->pmThreadManager->load($threadId);
    if ($private_message_thread && $private_message_thread->isMember($this->currentUser->id())) {
      $user = $this->userManager->load($this->currentUser->id());
      $messages = $private_message_thread->filterUserDeletedMessages($user);
      $start_index = FALSE;
      $settings = $this->configFactory->get('core.entity_view_display.private_message_thread.private_message_thread.default')->get('content.private_messages.settings');
      $count = $settings['ajax_previous_load_count'];
      foreach ($messages as $index => $message) {
        if ($message->id() >= $messageId) {
          $start_index = $index - $count >= 0 ? $index - $count : 0;
          $slice_count = $index > $count ? $count : $index;

          break;
        }
      }

      if ($start_index !== FALSE) {
        $messages = array_splice($messages, $start_index, $slice_count);
        if (count($messages)) {
          $order = $settings['message_order'];
          if ($order == 'desc') {
            $messages = array_reverse($messages);
          }

          $return = $messages;
        }
      }
    }

    return $return;
  }

  /**
   * {@inheritdoc}
   */
  public function getUsersFromString($string, $count) {
    $user_ids = $this->mapper->getUserIdsFromString($string, $count);

    $accounts = [];
    if (count($user_ids)) {
      $accounts = $this->userManager->loadMultiple($user_ids);
    }

    return $accounts;
  }

  /**
   * {@inheritdoc}
   */
  public function getUpdatedInboxThreads(array $existingThreadInfo, $count = FALSE) {
    $thread_info = $this->mapper->getUpdatedInboxThreadIds(array_keys($existingThreadInfo), $count);
    $new_threads = [];
    $thread_ids = [];
    $ids_to_load = [];
    foreach (array_keys($thread_info) as $thread_id) {
      $thread_ids[] = $thread_id;
      if (!isset($existingThreadInfo[$thread_id]) || $existingThreadInfo[$thread_id] != $thread_info[$thread_id]->updated) {
        $ids_to_load[] = $thread_id;
      }
    }

    if (count($ids_to_load)) {
      $new_threads = $this->pmThreadManager->loadMultiple($ids_to_load);
    }

    return [
      'thread_ids' => $thread_ids,
      'new_threads' => $new_threads,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function validatePrivateMessageMemberUsername($username) {
    return $this->mapper->checkPrivateMessageMemberExists($username);
  }

  /**
   * {@inheritdoc}
   */
  public function getUnreadThreadCount() {
    $uid = $this->currentUser->id();
    $last_check_timestamp = $this->userData->get(self::MODULE_KEY, $uid, self::LAST_CHECK_KEY);
    $last_check_timestamp = is_numeric($last_check_timestamp) ? $last_check_timestamp : 0;

    return (int) $this->mapper->getUnreadThreadCount($uid, $last_check_timestamp);
  }

  /**
   * {@inheritdoc}
   */
  public function updateLastCheckTime() {
    $uid = $this->currentUser->id();
    $this->userData->set(self::MODULE_KEY, $uid, self::LAST_CHECK_KEY, $this->time->getRequestTime());

    $tags[] = 'private_message_notification_block:uid:' . $this->currentUser->id();

    $this->cacheTagsInvalidator->invalidateTags($tags);
  }

  /**
   * {@inheritdoc}
   */
  public function getThreadFromMessage(PrivateMessageInterface $privateMessage) {
    $thread_id = $this->mapper->getThreadIdFromMessage($privateMessage);
    if ($thread_id) {
      return $this->pmThreadManager->load($thread_id);
    }

    return FALSE;
  }

  /**
   * Create a new private message thread for the given users.
   *
   * @param \Drupal\user\Entity\User[] $members
   *   An array of users who will be members of the given thread.
   *
   * @return \Drupal\private_message\Entity\PrivateMessageThread
   *   The new private message thread.
   */
  protected function createPrivateMessageThread(array $members) {
    $thread = $this->pmThreadManager->create();
    foreach ($members as $member) {
      $thread->addMember($member);
    }

    $thread->save();

    return $thread;
  }

}
