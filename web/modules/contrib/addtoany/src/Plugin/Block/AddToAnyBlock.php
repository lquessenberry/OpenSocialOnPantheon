<?php

namespace Drupal\addtoany\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * Provides an 'AddToAny' block.
 *
 * @Block(
 *   id = "addtoany_block",
 *   admin_label = @Translation("AddToAny buttons"),
 * )
 */
class AddToAnyBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    $node = \Drupal::routeMatch()->getParameter('node');

    return array(
      '#addtoany_html' => addtoany_create_node_buttons($node),
      '#theme' => 'addtoany_standard',
      '#cache' => array(
        'contexts' => array('url'),
      ),
    );
  }

}
