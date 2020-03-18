<?php

namespace Drupal\data_policy;

use Drupal\Core\Entity\Sql\SqlContentEntityStorage;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\data_policy\Entity\DataPolicyInterface;

/**
 * Defines the storage handler class for Data policy entities.
 *
 * This extends the base storage class, adding required special handling for
 * Data policy entities.
 *
 * @ingroup data_policy
 */
class DataPolicyStorage extends SqlContentEntityStorage implements DataPolicyStorageInterface {

  /**
   * {@inheritdoc}
   */
  public function revisionIds(DataPolicyInterface $entity) {
    return $this->database->query(
      'SELECT vid FROM {data_policy_revision} WHERE id=:id ORDER BY vid',
      [':id' => $entity->id()]
    )->fetchCol();
  }

  /**
   * {@inheritdoc}
   */
  public function userRevisionIds(AccountInterface $account) {
    return $this->database->query(
      'SELECT vid FROM {data_policy_field_revision} WHERE uid = :uid ORDER BY vid',
      [':uid' => $account->id()]
    )->fetchCol();
  }

  /**
   * {@inheritdoc}
   */
  public function countDefaultLanguageRevisions(DataPolicyInterface $entity) {
    return $this->database->query('SELECT COUNT(*) FROM {data_policy_field_revision} WHERE id = :id AND default_langcode = 1', [':id' => $entity->id()])
      ->fetchField();
  }

  /**
   * {@inheritdoc}
   */
  public function clearRevisionsLanguage(LanguageInterface $language) {
    return $this->database->update('data_policy_revision')
      ->fields(['langcode' => LanguageInterface::LANGCODE_NOT_SPECIFIED])
      ->condition('langcode', $language->getId())
      ->execute();
  }

}
