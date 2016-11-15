<?php

namespace Drupal\flag;


use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\Query\QueryFactory;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\user\UserInterface;

/**
 * Flag service.
 *  - Handles search requests for flags and flaggings.
 *  - Performs flagging and unflaging operations.
 */
class FlagService implements FlagServiceInterface {

  /**
   * The entity query manager injected into the service.
   *
   * @var \Drupal\Core\Entity\Query\QueryFactory
   */
  private $entityQueryManager;

  /**
   * The current user injected into the service.
   *
   * @var AccountInterface
   */
  private $currentUser;

  /*
   * @var EntityTypeManagerInterface
   * */
  private $entityTypeManager;

  /**
   * Constructor.
   *
   * @param QueryFactory $entity_query
   *   The entity query factory.
   * @param AccountInterface $current_user
   *   The current user.
   * @param EntityTypeManagerInterface $entity_type_manager
   *   The entity manager.
   */
  public function __construct(QueryFactory $entity_query,
                              AccountInterface $current_user,
                              EntityTypeManagerInterface $entity_type_manager) {
    $this->entityQueryManager = $entity_query;
    $this->currentUser = $current_user;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function getFlags($entity_type = NULL, $bundle = NULL, AccountInterface $account = NULL) {
    $query = $this->entityQueryManager->get('flag');

    if ($entity_type != NULL) {
      $query->condition('entity_type', $entity_type);
    }

    $ids = $query->execute();
    $flags = $this->getFlagsByIds($ids);

    if (isset($bundle)) {
      $flags = array_filter($flags, function (FlagInterface $flag) use ($bundle) {
        $bundles = $flag->getApplicableBundles();
        return in_array($bundle, $bundles);
      });
    }

    if ($account == NULL) {
      return $flags;
    }

    $filtered_flags = [];
    foreach ($flags as $flag) {
      if ($flag->actionAccess('flag', $account)->isAllowed() ||
          $flag->actionAccess('unflag', $account)->isAllowed()) {
        $filtered_flags[] = $flag;
      }
    }

    return $filtered_flags;
  }

  /**
   * {@inheritdoc}
   */
  public function getFlagging(FlagInterface $flag, EntityInterface $entity, AccountInterface $account = NULL) {
    if (empty($account)) {
      $account = $this->currentUser;
    }

    $flaggings = $this->getEntityFlaggings($flag, $entity, $account);

    return !empty($flaggings) ? reset($flaggings) : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getFlagFlaggings(FlagInterface $flag, AccountInterface $account = NULL) {
    $query = $this->entityQueryManager->get('flagging');

    $query->condition('flag_id', $flag->id());

    if (!empty($account) && !$flag->isGlobal()) {
      $query->condition('uid', $account->id());
    }

    $ids = $query->execute();

    return $this->getFlaggingsByIds($ids);
  }

  /**
   * {@inheritdoc}
   */
  public function getEntityFlaggings(FlagInterface $flag, EntityInterface $entity, AccountInterface $account = NULL) {
    $query = $this->entityQueryManager->get('flagging');

    $query->condition('flag_id', $flag->id());

    if (!empty($account) && !$flag->isGlobal()) {
      $query->condition('uid', $account->id());
    }
    if (isset($account) && $account->isAnonymous()) {
      $session = \Drupal::request()->getSession();
      if ($session && ($flaggings = $session->get('flaggings', []))) {
        $query->condition('id', $flaggings, 'IN');
      }
      else {
        return [];
      }
    }

    if (!empty($entity)) {
      $query->condition('entity_type', $entity->getEntityTypeId())
        ->condition('entity_id', $entity->id());
    }

    $ids = $query->execute();

    return $this->getFlaggingsByIds($ids);
  }

  /**
   * {@inheritdoc}
   */
  public function getAllEntityFlaggings(EntityInterface $entity, AccountInterface $account = NULL) {
    $query = $this->entityQueryManager->get('flagging');

    if (!empty($account)) {
      $query->condition('uid', $account->id());
    }

    $query->condition('entity_type', $entity->getEntityTypeId())
      ->condition('entity_id', $entity->id());

    $ids = $query->execute();

    return $this->getFlaggingsByIds($ids);
  }

  /**
   * {@inheritdoc}
   */
  public function getFlagById($flag_id) {
    return $this->entityTypeManager->getStorage('flag')->load($flag_id);
  }

  /**
   * {@inheritdoc}
   */
  public function getFlaggableById(FlagInterface $flag, $entity_id) {
    return $this->entityTypeManager->getStorage($flag->getFlaggableEntityTypeId())
      ->load($entity_id);
  }

  /**
   * {@inheritdoc}
   */
  public function getFlaggingUsers(EntityInterface $entity, FlagInterface $flag = NULL) {
    $query = $this->entityQueryManager->get('flagging')
      ->condition('entity_type', $entity->getEntityTypeId())
      ->condition('entity_id', $entity->id());

    if (!empty($flag)) {
      $query->condition('flag_id', $flag->id());
    }

    $ids = $query->execute();
    // Load the flaggings.
    $flaggings = $this->getFlaggingsByIds($ids);

    $user_ids = array();
    foreach ($flaggings as $flagging) {
      $user_ids[] = $flagging->get('uid')->first()->getValue()['target_id'];
    }

    return $this->entityTypeManager->getStorage('user')
      ->loadMultiple($user_ids);
  }

  /**
   * {@inheritdoc}
   */
  public function flag(FlagInterface $flag, EntityInterface $entity, AccountInterface $account = NULL) {
    $bundles = $flag->getBundles();

    if (empty($account)) {
      $account = $this->currentUser;
    }

    // Check the entity type corresponds to the flag type.
    if ($flag->getFlaggableEntityTypeId() != $entity->getEntityTypeId()) {
      throw new \LogicException('The flag does not apply to entities of this type.');
    }

    // Check the bundle is allowed by the flag.
    if (!empty($bundles) && !in_array($entity->bundle(), $bundles)) {
      throw new \LogicException('The flag does not apply to the bundle of the entity.');
    }

    // Check whether there is an existing flagging for the combination of flag,
    // entity, and user.
    if ($this->getFlagging($flag, $entity, $account)) {
      throw new \LogicException('The user has already flagged the entity with the flag.');
    }

    $flagging = $this->entityTypeManager->getStorage('flagging')->create([
      'uid' => $account->id(),
      'flag_id' => $flag->id(),
      'entity_id' => $entity->id(),
      'entity_type' => $entity->getEntityTypeId(),
      'global' => $flag->isGlobal(),
    ]);

    $flagging->save();

    return $flagging;
  }

  /**
   * {@inheritdoc}
   */
  public function unflag(FlagInterface $flag, EntityInterface $entity, AccountInterface $account = NULL) {
    $bundles = $flag->getBundles();

    // Check the entity type corresponds to the flag type.
    if ($flag->getFlaggableEntityTypeId() != $entity->getEntityTypeId()) {
      throw new \LogicException('The flag does not apply to entities of this type.');
    }

    // Check the bundle is allowed by the flag.
    if (!empty($bundles) && !in_array($entity->bundle(), $bundles)) {
      throw new \LogicException('The flag does not apply to the bundle of the entity.');
    }

    $flagging = $this->getFlagging($flag, $entity, $account);

    // Check whether there is an existing flagging for the combination of flag,
    // entity, and user.
    if (!$flagging) {
      throw new \LogicException('The entity is not flagged by the user.');
    }

    $flagging->delete();
  }

  /**
   * {@inheritdoc}
   */
  public function unflagAllByFlag(FlagInterface $flag) {
    $query = $this->entityQueryManager->get('flagging');

    $query->condition('flag_id', $flag->id());

    $ids = $query->execute();

    $flaggings = $this->getFlaggingsByIds($ids);

    $this->entityTypeManager->getStorage('flagging')->delete($flaggings);
  }

  /**
   * {@inheritdoc}
   */
  public function unflagAllByEntity(EntityInterface $entity) {
    $query = $this->entityQueryManager->get('flagging');

    $query->condition('entity_type', $entity->getEntityTypeId())
      ->condition('entity_id', $entity->id());

    $ids = $query->execute();

    $flaggings = $this->getFlaggingsByIds($ids);

    $this->entityTypeManager->getStorage('flagging')->delete($flaggings);
  }

  /**
   * {@inheritdoc}
   */
  public function unflagAllByUser(AccountInterface $account) {
    $query = $this->entityQueryManager->get('flagging');

    $query->condition('uid', $account->id());

    $ids = $query->execute();

    $flaggings = $this->getFlaggingsByIds($ids);

    $this->entityTypeManager->getStorage('flagging')->delete($flaggings);
  }

  /**
   * {@inheritdoc}
   */
  public function userFlagRemoval(UserInterface $account) {
    // Remove flags by this user.
    $this->unflagAllByUser($account);

    // Remove flags that have been done to this user.
    $this->unflagAllByEntity($account);
  }

  /**
   * Loads flag entities given their IDs.
   *
   * @param int[] $ids
   *   The flag IDs.
   *
   * @return \Drupal\flag\FlagInterface[]
   *   An array of flags.
   */
  protected function getFlagsByIds(array $ids) {
    return $this->entityTypeManager->getStorage('flag')->loadMultiple($ids);
  }

  /**
   * Loads flagging entities given their IDs.
   *
   * @param int[] $ids
   *   The flagging IDs.
   *
   * @return \Drupal\flag\FlaggingInterface[]
   *   An array of flaggings.
   */
  protected function getFlaggingsByIds(array $ids) {
    return $this->entityTypeManager->getStorage('flagging')->loadMultiple($ids);
  }

}
