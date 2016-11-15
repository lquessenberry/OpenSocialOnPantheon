<?php

namespace Drupal\flag\Plugin\ActionLink;

use Drupal\flag\ActionLink\ActionLinkTypeBase;

/**
 * Provides the Normal Link (Reload) link type.
 *
 * @ActionLinkType(
 *   id = "reload",
 *   label = @Translation("Normal link"),
 *   description = "A normal non-JavaScript request will be made and the current page will be reloaded.")
 */
class Reload extends ActionLinkTypeBase {

  /**
   * {@inheritdoc}
   */
  public function routeName($action = NULL) {
    if ($action === 'unflag') {
      return 'flag.action_link_unflag';
    }

    return 'flag.action_link_flag';
  }

}
