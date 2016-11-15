<?php

namespace Drupal\flag\ActionLink;

use Drupal\Core\Entity\EntityInterface;
use Drupal\flag\FlagInterface;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\Component\Plugin\ConfigurablePluginInterface;

/**
 * Provides an interface for link type plugins.
 */
interface ActionLinkTypePluginInterface extends PluginFormInterface, ConfigurablePluginInterface {

  /**
   * Returns a flag link as a render array.
   *
   * The link's action is determined from the current state of the flag.
   *
   * If the current user does not have access to the flag then an empty render
   * array will be returned.
   *
   * @param FlagInterface $flag
   *   The flag entity.
   * @param EntityInterface $entity
   *   The entity for which to create a flag link.
   *
   * @return array
   *   A render array of the flag link.
   */
  public function getLink(FlagInterface $flag, EntityInterface $entity);

  /**
   * Returns a Url object for the given flag action.
   *
   * This method is not recommended for general use.
   *
   * @see \Drupal\flag\ActionLink\ActionLinkTypePluginInterface::getLink()
   *
   * @param string $action
   *   The action, flag or unflag.
   * @param \Drupal\flag\FlagInterface $flag
   *   The flag entity
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity.
   *
   * @return \Drupal\Core\Url
   *   The URL object.
   */
  public function getLinkURL($action, FlagInterface $flag, EntityInterface $entity);

  /**
   * Generates a flag link as a render array.
   *
   * This method is not recommended for general use.
   *
   * @see \Drupal\flag\ActionLink\ActionLinkTypePluginInterface::getLink()
   *
   * @param string $action
   *   The action to perform, 'flag' or 'unflag'.
   * @param FlagInterface $flag
   *   The flag entity.
   * @param EntityInterface $entity
   *   The entity for which to create a flag link.
   *
   * @return array
   *   A render array of the flag link.
   */
  public function buildLink($action, FlagInterface $flag, EntityInterface $entity);

}
