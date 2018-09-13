<?php

namespace Drupal\flag\ActionLink;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Link;
use Drupal\flag\FlagInterface;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\Component\Plugin\ConfigurablePluginInterface;

/**
 * Provides an interface for link type plugins.
 */
interface ActionLinkTypePluginInterface extends PluginFormInterface, ConfigurablePluginInterface {

  /**
   * Get the action link formatted for use in entity links.
   *
   * @param \Drupal\flag\FlagInterface $flag
   *   The flag entity.
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The flaggable entity.
   *
   * @return array
   *   The render array.
   */
  public function getAsFlagLink(FlagInterface $flag, EntityInterface $entity);

  /**
   * Get the action link as a Link object.
   *
   * @param \Drupal\flag\FlagInterface $flag
   *   The flag entity.
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The flaggable entity.
   *
   * @return \Drupal\Core\Link
   *   The action Link.
   */
  public function getAsLink(FlagInterface $flag, EntityInterface $entity);

}
