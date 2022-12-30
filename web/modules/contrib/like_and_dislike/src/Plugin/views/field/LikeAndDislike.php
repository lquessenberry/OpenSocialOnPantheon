<?php

namespace Drupal\like_and_dislike\Plugin\views\field;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\like_and_dislike\LikeDislikeVoteBuilderInterface;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Like and Dislike field handler.
 *
 * @ingroup views_field_handlers
 *
 * @ViewsField("like_and_dislike")
 */
class LikeAndDislike extends FieldPluginBase implements ContainerFactoryPluginInterface {

  /**
   * LikeDislikeVoteBuilder definition.
   *
   * @var \Drupal\like_and_dislike\LikeDislikeVoteBuilderInterface
   */
  protected $likeDislikeBuilder;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('like_and_dislike.vote_builder')
    );
  }

  /**
   * LikeAndDislike constructor.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\like_and_dislike\LikeDislikeVoteBuilderInterface $likeDislikeBuilder
   *   Like and Dislike builder.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    LikeDislikeVoteBuilderInterface $likeDislikeBuilder
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->likeDislikeBuilder = $likeDislikeBuilder;
  }

  /**
   * {@inheritdoc}
   */
  public function query() {
    // We don't need to modify query.
  }

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values) {
    if ($entity = $values->_entity) {
      if (like_and_dislike_is_enabled($entity)) {
        $entity_type = $entity->getEntityTypeId();
        $entity_id = $entity->id();
        return $this->likeDislikeBuilder->build($entity_type, $entity_id);
      }
      else {
        return $this->t('Enable the current entity/bundle in the Like & Dislike settings page.');
      }
    }
    return NULL;
  }

}
