<?php

namespace Drupal\private_message\Mapper;

use Drupal\Core\Database\Connection;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\private_message\Entity\PrivateMessageInterface;
use Drupal\user\UserInterface;

/**
 * Interface for the Private Message Mapper class.
 */
class PrivateMessageMapper implements PrivateMessageMapperInterface {

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * The current user.
   *
   * @var \Drupal\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * Constructs a PrivateMessageMapper object.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   * @param \Drupal\Session\AccountProxyInterface $currentUser
   *   The current user.
   */
  public function __construct(Connection $database, AccountProxyInterface $currentUser) {
    $this->database = $database;
    $this->currentUser = $currentUser;
  }

  /**
   * {@inheritdoc}
   */
  public function getThreadIdForMembers(array $members) {
    $uids = [];
    foreach ($members as $member) {
      $uids[] = $member->id();
    }

    $query = $this->database->select('private_message_threads', 'pm')
      ->fields('pm', ['id'])
      ->range(0, 1);

    // First do an inner join for each user, to ensure that the user exists in
    // the theread.
    $i = 0;
    foreach ($uids as $uid) {
      $tmp_alias = 'member_' . $i;
      $query->join('private_message_thread__members', $tmp_alias, $tmp_alias . '.entity_id = pm.id AND ' . $tmp_alias . '.members_target_id = :uid' . $i, [':uid' . $i => $uid]);
      $i++;
    }

    // Next, do a left join for all rows that don't contain the users, and
    // ensure that there aren't any additional users in selected threads.
    $alias = $query->leftJoin('private_message_thread__members', 'member', 'member.entity_id = pm.id AND member.members_target_id NOT IN (:uids[])', [':uids[]' => $uids]);
    $query->isNull($alias . '.members_target_id');

    return $query->execute()->fetchField();
  }

  /**
   * {@inheritdoc}
   */
  public function getFirstThreadIdForUser(UserInterface $user) {
    return $this->database->queryRange('SELECT thread.id ' .
      'FROM {private_message_threads} AS thread ' .
      'JOIN {private_message_thread__members} AS member ' .
      'ON member.entity_id = thread.id AND member.members_target_id = :uid ' .
      'JOIN {private_message_thread__private_messages} AS thread_messages ' .
      'ON thread_messages.entity_id = thread.id ' .
      'JOIN {private_messages} AS messages ' .
      'ON messages.id = thread_messages.private_messages_target_id ' .
      'JOIN {private_message_thread__last_delete_time} AS thread_delete_time ' .
      'ON thread_delete_time.entity_id = thread.id ' .
      'JOIN {pm_thread_delete_time} as owner_delete_time ' .
      'ON owner_delete_time.id = thread_delete_time.last_delete_time_target_id AND owner_delete_time.owner = :uid ' .
      'WHERE owner_delete_time.delete_time <= messages.created ' .
      'ORDER BY thread.updated DESC',
      0,
      1,
      [':uid' => $user->id()]
    )->fetchField();
  }

  /**
   * {@inheritdoc}
   */
  public function getThreadIdsForUser(UserInterface $user, $count, $timestamp = FALSE) {
    $query = 'SELECT DISTINCT(thread.id), MAX(thread.updated) ' .
      'FROM {private_message_threads} AS thread ' .
      'JOIN {private_message_thread__members} AS member ' .
      'ON member.entity_id = thread.id AND member.members_target_id = :uid ' .
      'JOIN {private_message_thread__private_messages} AS thread_messages ' .
      'ON thread_messages.entity_id = thread.id ' .
      'JOIN {private_messages} AS messages ' .
      'ON messages.id = thread_messages.private_messages_target_id ' .
      'JOIN {private_message_thread__last_delete_time} AS thread_delete_time ' .
      'ON thread_delete_time.entity_id = thread.id ' .
      'JOIN {pm_thread_delete_time} as owner_delete_time ' .
      'ON owner_delete_time.id = thread_delete_time.last_delete_time_target_id AND owner_delete_time.owner = :uid ' .
      'WHERE owner_delete_time.delete_time <= messages.created ';
    $vars = [':uid' => $user->id()];

    if ($timestamp) {
      $query .= 'AND updated < :timestamp ';
      $vars[':timestamp'] = $timestamp;
    }

    $query .= 'GROUP BY thread.id ORDER BY MAX(thread.updated) DESC, thread.id';

    $thread_ids = $this->database->queryRange(
      $query,
      0, $count,
      $vars
    )->fetchCol();

    return is_array($thread_ids) ? $thread_ids : [];
  }

  /**
   * {@inheritdoc}
   */
  public function checkForNextThread(UserInterface $user, $timestamp) {
    $query = 'SELECT DISTINCT(thread.id) ' .
      'FROM {private_message_threads} AS thread ' .
      'JOIN {private_message_thread__members} AS member ' .
      'ON member.entity_id = thread.id AND member.members_target_id = :uid ' .
      'JOIN {private_message_thread__private_messages} AS thread_messages ' .
      'ON thread_messages.entity_id = thread.id ' .
      'JOIN {private_messages} AS messages ' .
      'ON messages.id = thread_messages.private_messages_target_id ' .
      'JOIN {private_message_thread__last_delete_time} AS message_delete_time ' .
      'ON message_delete_time.entity_id = thread.id ' .
      'JOIN {pm_thread_delete_time} as owner_delete_time ' .
      'ON owner_delete_time.id = message_delete_time.last_delete_time_target_id ' .
      'WHERE owner_delete_time.delete_time <= messages.created ' .
      'AND thread.updated < :timestamp';
    $vars = [
      ':uid' => $user->id(),
      ':timestamp' => $timestamp,
    ];

    return (bool) $this->database->queryRange(
      $query,
      0, 1,
      $vars
    )->fetchField();
  }

  /**
   * {@inheritdoc}
   */
  public function getUserIdsFromString($string, $count) {
    if ($this->currentUser->hasPermission('access user profiles') && $this->currentUser->hasPermission('use private messaging system')) {
      $query = 'SELECT user_data.uid FROM {users_field_data} AS user_data LEFT ' .
        'JOIN {user__roles} AS user_roles ' .
        'ON user_roles.entity_id = user_data.uid ' .
        'LEFT JOIN {config} AS role_config ' .
        "ON role_config.name = CONCAT('user.role.', user_roles.roles_target_id) " .
        'JOIN {config} AS config ON config.name = :authenticated_config WHERE ' .
        'user_data.name LIKE :string AND user_data.name != :current_user AND ' .
        '(config.data LIKE :use_pm_permission ' .
        'OR role_config.data LIKE :use_pm_permission) ' .
        'ORDER BY user_data.name ASC';

      return $this->database->queryRange(
        $query,
        0,
        $count,
        [
          ':string' => $string . '%',
          ':current_user' => $this->currentUser->getAccountName(),
          ':authenticated_config' => 'user.role.authenticated',
          ':use_pm_permission' => '%s:28:"use private messaging system"%',
          ':access_user_profiles_permission' => '%s:20:"access user profiles"%',
        ]
      )->fetchCol();
    }
    else {
      return [];
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getUpdatedInboxThreadIds(array $existingThreadIds, $count = FALSE) {
    $query = 'SELECT DISTINCT(thread.id), updated ' .
      'FROM {private_message_threads} AS thread ' .
      'JOIN {private_message_thread__members} AS member ' .
      'ON member.entity_id = thread.id AND member.members_target_id = :uid ' .
      'JOIN {private_message_thread__private_messages} AS thread_messages ' .
      'ON thread_messages.entity_id = thread.id ' .
      'JOIN {private_messages} AS messages ' .
      'ON messages.id = thread_messages.private_messages_target_id ' .
      'JOIN {private_message_thread__last_delete_time} AS message_delete_time ' .
      'ON message_delete_time.entity_id = thread.id ' .
      'JOIN {pm_thread_delete_time} as owner_delete_time ' .
      'ON owner_delete_time.id = message_delete_time.last_delete_time_target_id ' .
      'WHERE owner_delete_time.delete_time <= messages.created ';
    $vars = [':uid' => $this->currentUser->id()];
    $order_by = 'ORDER BY thread.updated DESC';
    if (count($existingThreadIds)) {
      $query .= 'AND thread.updated >= (SELECT MIN(updated) FROM {private_message_threads} WHERE id IN (:ids[])) ';
      $vars[':ids[]'] = $existingThreadIds;

      return $this->database->query($query . $order_by, $vars)->fetchAllAssoc('id');
    }
    else {
      return $this->database->queryRange($query . $order_by, 0, $count, $vars)->fetchAllAssoc('id');
    }
  }

  /**
   * {@inheritdoc}
   */
  public function checkPrivateMessageMemberExists($username) {
    return $this->database->queryRange(
      'SELECT 1 FROM {users_field_data} AS user_data LEFT JOIN {user__roles} AS ' .
      'user_roles ON user_roles.entity_id = user_data.uid  LEFT JOIN {config} ' .
      'AS role_config ' .
      "ON role_config.name = CONCAT('user.role.', user_roles.roles_target_id) " .
      'LEFT JOIN {config} AS authenticated_config ' .
      'ON authenticated_config.name = :authenticated_user_role ' .
      'WHERE user_data.name = :username ' .
      'AND (role_config.data LIKE :permission OR authenticated_config.data LIKE :permission)',
      0,
      1,
      [
        ':username' => $username,
        ':authenticated_user_role' => 'user.role.authenticated',
        ':permission' => '%use private messaging system%',
      ]
    )->fetchField();
  }

  /**
   * {@inheritdoc}
   */
  public function getUnreadThreadCount($uid, $lastCheckTimestamp) {
    return $this->database->query(
      'SELECT COUNT(thread.id) FROM {private_message_threads} AS thread JOIN ' .
      '{private_message_thread__members} AS member ' .
      'ON member.entity_id = thread.id AND member.members_target_id = :uid ' .
      'WHERE thread.updated > :timestamp',
      [
        ':uid' => $uid,
        ':timestamp' => $lastCheckTimestamp,
      ]
    )->fetchField();
  }

  /**
   * {@inheritdoc}
   */
  public function getThreadIdFromMessage(PrivateMessageInterface $privateMessage) {
    return $this->database->queryRange(
      'SELECT thread.id FROM {private_message_threads} AS thread JOIN ' .
      '{private_message_thread__private_messages} AS messages ' .
      'ON messages.entity_id = thread.id AND messages.private_messages_target_id = :message_id',
      0,
      1,
      [
        ':message_id' => $privateMessage->id(),
      ]
    )->fetchField();
  }

}
