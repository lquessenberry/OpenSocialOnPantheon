<?php

namespace Drupal\like_and_dislike;

/**
 * Provides a lazy builder interface for user votes.
 */
interface LikeDislikeVoteBuilderInterface {

  /**
   * Lazy builder callback for displaying like and dislike icons.
   *
   * @param string $entity_type_id
   *   The entity type ID for which like and dislikes icons should be shown.
   * @param string|int $entity_id
   *   The entity ID for which like and dislikes icons should be shown.
   *
   * @return array
   *   A render array for like and dislike icons.
   */
  public function build($entity_type_id, $entity_id);

}
