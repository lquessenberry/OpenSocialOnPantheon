<?php

namespace Drupal\like_and_dislike\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Drupal\Core\Access\AccessResult;

/**
 * Returns responses for Like & Dislikes routes.
 */
class VoteController extends ControllerBase {

  /**
   * Creates a vote for a given parameters.
   *
   * @param string $entity_type_id
   *   The entity type ID to vote for.
   * @param string $vote_type_id
   *   The vote type (like or dislike).
   * @param string $entity_id
   *   The entity ID to vote for.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   Returns JSON response.
   */
  public function vote($entity_type_id, $vote_type_id, $entity_id) {
    $entity = $this->entityTypeManager()->getStorage($entity_type_id)->load($entity_id);

    // Gets the number of likes and dislikes for the entity.
    list($like, $dislike) = like_and_dislike_get_votes($entity);
    $operation = ['like' => FALSE, 'dislike' => FALSE];

    /** @var \Drupal\votingapi\VoteStorageInterface $vote_storage */
    $vote_storage = $this->entityTypeManager()->getStorage('vote');
    $user_votes = $vote_storage->getUserVotes(
      $this->currentUser()->id(), $vote_type_id, $entity_type_id, $entity_id
    );

    if ($vote_type_id === 'like') {
      $opposite_vote_type_id = 'dislike';
    }
    else {
      $opposite_vote_type_id = 'like';
    }

    if (empty($user_votes)) {
      // Increment the value for requested vote type.
      $$vote_type_id++;
      $operation[$vote_type_id] = TRUE;

      // @todo: Moving it after vote creation wrongly returns empty array.
      // Get user votes for opposite vote type.
      $user_opposite_votes = $vote_storage->getUserVotes(
        $this->currentUser()->id(), $opposite_vote_type_id, $entity_type_id, $entity_id
      );

      /** @var \Drupal\votingapi\VoteTypeInterface $vote_type */
      $vote_type = $this->entityTypeManager()->getStorage('vote_type')->load($vote_type_id);
      /** @var \Drupal\votingapi\VoteInterface $vote */
      $vote = $vote_storage->create(['type' => $vote_type_id]);
      $vote->setVotedEntityId($entity_id);
      $vote->setVotedEntityType($entity_type_id);
      $vote->setValueType($vote_type->getValueType());
      $vote->setValue(1);
      $vote->save();

      // Remove the opposite vote, if any.
      if (!empty($user_opposite_votes)) {
        $vote_storage->deleteUserVotes(
          $this->currentUser()->id(), $opposite_vote_type_id, $entity_type_id, $entity_id
        );
        // Remove opposite vote.
        $$opposite_vote_type_id--;
        $operation[$opposite_vote_type_id] = FALSE;
      }

      // Clear the view builder's cache.
      $this->entityTypeManager()->getViewBuilder($entity_type_id)->resetCache([$entity]);

      return new JsonResponse([
        'likes' => $like,
        'dislikes' => $dislike,
        'message_type' => 'status',
        'operation' => $operation,
        'message' => $this->t('Your vote was added.'),
      ]);
    }
    else {
      if ($this->config('like_and_dislike.settings')->get('allow_cancel_vote')) {
        // Decrement the value for requested vote type.
        $$vote_type_id--;
        $operation[$vote_type_id] = FALSE;

        // Remove the vote.
        $vote_storage->deleteUserVotes(
          $this->currentUser()->id(), $vote_type_id, $entity_type_id, $entity_id
        );
        // Clear the view builder's cache.
        $this->entityTypeManager()->getViewBuilder($entity_type_id)->resetCache([$entity]);

        return new JsonResponse([
          'likes' => $like,
          'dislikes' => $dislike,
          'operation' => $operation,
          'message_type' => 'status',
          'message' => $this->t('Your vote was canceled.'),
        ]);
      }
      else {
        // User is not allowed to cancel his vote.
        return new JsonResponse([
          'likes' => $like,
          'dislikes' => $dislike,
          'operation' => $operation,
          'message_type' => 'warning',
          'message' => $this->t('You are not allowed to vote the same way multiple times.'),
        ]);
      }
    }
  }

  /**
   * Checks if the currentUser is allowed to vote.
   *
   * @param string $entity_type_id
   *   The entity type ID.
   * @param string $vote_type_id
   *   The vote type ID.
   * @param string $entity_id
   *   The entity ID.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function voteAccess($entity_type_id, $vote_type_id, $entity_id) {
    $entity = $this->entityTypeManager()->getStorage($entity_type_id)->load($entity_id);
    // Check if user has permission to vote.
    return AccessResult::allowedIf(like_and_dislike_can_vote($this->currentUser(), $vote_type_id, $entity));
  }

}
