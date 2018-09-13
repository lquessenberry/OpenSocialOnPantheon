<?php

namespace Drupal\data_policy;

use Drupal\Core\Entity\ContentEntityStorageInterface;
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
interface DataPolicyStorageInterface extends ContentEntityStorageInterface {

  /**
   * Gets a list of Data policy revision IDs for a specific Data policy.
   *
   * @param \Drupal\data_policy\Entity\DataPolicyInterface $entity
   *   The Data policy entity.
   *
   * @return int[]
   *   Data policy revision IDs (in ascending order).
   */
  public function revisionIds(DataPolicyInterface $entity);

  /**
   * Gets a list of revision IDs having a given user as Data policy author.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user entity.
   *
   * @return int[]
   *   Data policy revision IDs (in ascending order).
   */
  public function userRevisionIds(AccountInterface $account);

  /**
   * Counts the number of revisions in the default language.
   *
   * @param \Drupal\data_policy\Entity\DataPolicyInterface $entity
   *   The Data policy entity.
   *
   * @return int
   *   The number of revisions in the default language.
   */
  public function countDefaultLanguageRevisions(DataPolicyInterface $entity);

  /**
   * Unsets the language for all Data policy with the given language.
   *
   * @param \Drupal\Core\Language\LanguageInterface $language
   *   The language object.
   */
  public function clearRevisionsLanguage(LanguageInterface $language);

}
