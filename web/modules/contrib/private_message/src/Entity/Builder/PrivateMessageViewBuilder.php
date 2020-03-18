<?php

namespace Drupal\private_message\Entity\Builder;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityViewBuilder;

/**
 * Build handler for rpivate messages.
 */
class PrivateMessageViewBuilder extends EntityViewBuilder {

  /**
   * {@inheritdoc}
   */
  public function view(EntityInterface $entity, $view_mode = 'full', $langcode = NULL) {
    $build = parent::view($entity, $view_mode, $langcode);

    $classes = ['private-message'];
    $classes[] = 'private-message-' . $view_mode;

    $build['#prefix'] = '<div id="private-message-' . $entity->id() . '" data-message-id="' . $entity->id() . '" class="' . implode(' ', $classes) . '">';
    $build['#suffix'] = '</div>';

    return $build;
  }

}
