<?php

namespace Drupal\flag\Plugin\ActionLink;

use Drupal\flag\ActionLink\ActionLinkTypeBase;
use Drupal\Core\Entity\EntityInterface;
use Drupal\flag\FlagInterface;

/**
 * Provides the AJAX link type.
 *
 * This class is an extension of the Reload link type, but modified to
 * provide AJAX links.
 *
 * @ActionLinkType(
 *   id = "ajax_link",
 *   label = @Translation("AJAX link"),
 *   description = "An AJAX JavaScript request will be made without reloading the page."
 * )
 */
class AJAXactionLink extends Reload {

  /**
   * {@inheritdoc}
   */
  public function buildLink($action, FlagInterface $flag, EntityInterface $entity) {
    $render = parent::buildLink($action, $flag, $entity);
    $render['#attached']['library'][] = 'core/drupal.ajax';
    $render['#attributes']['class'][] = 'use-ajax';
    return $render;
  }

}
