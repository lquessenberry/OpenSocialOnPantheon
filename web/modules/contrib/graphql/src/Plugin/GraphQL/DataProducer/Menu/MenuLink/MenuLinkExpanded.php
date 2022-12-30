<?php

namespace Drupal\graphql\Plugin\GraphQL\DataProducer\Menu\MenuLink;

use Drupal\Core\Menu\MenuLinkInterface;
use Drupal\graphql\Plugin\GraphQL\DataProducer\DataProducerPluginBase;

/**
 * Returns whether a menu link is expanded.
 *
 * @todo Fix input context type.
 *
 * @DataProducer(
 *   id = "menu_link_expanded",
 *   name = @Translation("Menu link expanded"),
 *   description = @Translation("Returns whether a menu link is expanded."),
 *   produces = @ContextDefinition("boolean",
 *     label = @Translation("Expanded")
 *   ),
 *   consumes = {
 *     "link" = @ContextDefinition("any",
 *       label = @Translation("Menu link")
 *     )
 *   }
 * )
 */
class MenuLinkExpanded extends DataProducerPluginBase {

  /**
   * Resolver.
   *
   * @param \Drupal\Core\Menu\MenuLinkInterface $link
   *
   * @return bool
   */
  public function resolve(MenuLinkInterface $link) {
    return $link->isExpanded();
  }

}
