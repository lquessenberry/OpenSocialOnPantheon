<?php

namespace Drupal\like_and_dislike;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Template\Attribute;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Security\TrustedCallbackInterface;

/**
 * Provides a lazy builder for user votes.
 */
class LikeDislikeVoteBuilder implements LikeDislikeVoteBuilderInterface, TrustedCallbackInterface {

  use StringTranslationTrait;

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
   * The account.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Constructs a new LikeDislikeVoteBuilder.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The configuration factory.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, AccountInterface $current_user, ConfigFactoryInterface $config_factory) {
    $this->entityTypeManager = $entity_type_manager;
    $this->voteStorage = $entity_type_manager->getStorage('vote');
    $this->currentUser = $current_user;
    $this->configFactory = $config_factory;
  }

  /**
   * {@inheritdoc}
   */
  public function build($entity_type_id, $entity_id) {
    // Load the entity for which like and dislikes icons should be shown.
    $entity = $this->entityTypeManager->getStorage($entity_type_id)->load($entity_id);

    $hide_vote_widget = $this->configFactory->get('like_and_dislike.settings')->get('hide_vote_widget');

    $like_access = like_and_dislike_can_vote($this->currentUser, 'like', $entity);
    $dislike_access = like_and_dislike_can_vote($this->currentUser, 'dislike', $entity);
    list($likes, $dislikes) = like_and_dislike_get_votes($entity);

    $icons = [];
    // Like icon.
    if (!$hide_vote_widget || $like_access) {
      $like_attributes = new Attribute([
        'title' => $this->t('Like'),
        'data-entity-id' => $entity_id,
        'data-entity-type' => $entity_type_id,
      ]);
      if (!$like_access) {
        $like_attributes->addClass('disable-status');
      }
      if ((bool) $this->voteStorage->getUserVotes($this->currentUser->id(), 'like', $entity_type_id, $entity_id)) {
        $like_attributes->addClass('voted');
      }
      $icons['like'] = [
        'count' => $likes,
        'label' => $this->t('Like'),
        'attributes' => $like_attributes,
      ];
    }
    // Dislike icon.
    if (!$hide_vote_widget || $dislike_access) {
      $dislike_attributes = new Attribute([
        'title' => $this->t('Dislike'),
        'data-entity-id' => $entity_id,
        'data-entity-type' => $entity_type_id,
      ]);
      if ((bool) $this->voteStorage->getUserVotes($this->currentUser->id(), 'dislike', $entity_type_id, $entity_id)) {
        $dislike_attributes->addClass('voted');
      }
      if (!$dislike_access) {
        $dislike_attributes->addClass('disable-status');
      }
      $icons['dislike'] = [
        'count' => $dislikes,
        'label' => $this->t('Dislike'),
        'attributes' => $dislike_attributes,
      ];
    }

    $build['icons'] = [
      '#theme' => 'like_and_dislike_icons',
      '#attached' => ['library' => ['like_and_dislike/icons']],
      '#entity_id' => $entity_id,
      '#entity_type' => $entity_type_id,
      '#icons' => $icons,
    ];

    // Attach JS logic in case user has enough permissions to vote.
    if ($like_access || $dislike_access) {
      $build['icons']['#attached']['library'][] = 'like_and_dislike/behavior';
    }

    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public static function trustedCallbacks() {
    return ['build'];
  }

}
