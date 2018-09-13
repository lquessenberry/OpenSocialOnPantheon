<?php

namespace Drupal\like_and_dislike;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Template\Attribute;

/**
 * Provides a lazy builder for user votes.
 */
class LikeDislikeVoteBuilder implements LikeDislikeVoteBuilderInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The voting storage.
   *
   * @var \Drupal\votingapi\VoteStorageInterface
   */
  protected $voteStorage;

  /**
   * Constructs a new LikeDislikeVoteBuilder.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
    $this->voteStorage = $entity_type_manager->getStorage('vote');
  }

  /**
   * {@inheritdoc}
   */
  public function build($entity_type_id, $entity_id) {

    // If either the entity type or the entity id is not set,
    // return and empty array.
    if (empty($entity_type_id) || empty($entity_id)) {
      return [];
    }

    $account = \Drupal::currentUser();
    // Load the entity for which like and dislikes icons should be shown.
    $entity = $this->entityTypeManager->getStorage($entity_type_id)->load($entity_id);

    $like_access = like_and_dislike_can_vote($account, 'like', $entity);
    $dislike_access = like_and_dislike_can_vote($account, 'dislike', $entity);
    list($likes, $dislikes) = like_and_dislike_get_votes($entity);

    $user_liked = (bool) $this->voteStorage->getUserVotes($account->id(), 'like', $entity_type_id, $entity_id);
    $user_disliked = (bool) $this->voteStorage->getUserVotes($account->id(), 'dislike', $entity_type_id, $entity_id);

    $like_attributes = new Attribute([
      'title' => t('Like'),
      'data-entity-id' => $entity_id,
      'data-entity-type' => $entity_type_id,
    ]);
    $dislike_attributes = new Attribute([
      'title' => t('Dislike'),
      'data-entity-id' => $entity_id,
      'data-entity-type' => $entity_type_id,
    ]);
    if ($user_liked) {
      $like_attributes->addClass('voted-like');
    }
    if (!$like_access) {
      $like_attributes->addClass('disable-status');
    }
    if ($user_disliked) {
      $dislike_attributes->addClass('voted-dislike');
    }
    if (!$dislike_access) {
      $dislike_attributes->addClass('disable-status');
    }

    $build['icons'] = [
      '#theme' => 'like_and_dislike_icons',
      '#attached' => ['library' => ['like_and_dislike/icons']],
      '#entity_id' => $entity_id,
      '#entity_type' => $entity_type_id,
      '#likes' => $likes,
      '#dislikes' => $dislikes,
      '#like_attributes' => $like_attributes,
      '#dislike_attributes' => $dislike_attributes,
    ];

    // Attach JS logic in case user has enough permissions to vote.
    if ($like_access || $dislike_access) {
      $build['icons']['#attached']['library'][] = 'like_and_dislike/behavior';
    }

    return $build;
  }

}
