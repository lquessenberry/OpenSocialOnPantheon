<?php

namespace Drupal\social_api\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines a Social Network item annotation object.
 *
 * @see \Drupal\social_api\Plugin\NetworkManager
 * @see plugin_api
 *
 * @Annotation
 */
class Network extends Plugin {

  /**
   * The module machine name.
   *
   * @var string
   */
  public $id;

  /**
   * The social network service implemented by the plugin.
   *
   * @var \Drupal\Core\Annotation\Translation
   *
   * @ingroup plugin_translatable
   */
  public $social_network;

  /**
   * The type of the plugin.
   *
   * @var string
   */
  public $type;

  /**
   * A list of extra handlers.
   *
   * @var array
   *
   * @todo Check the entity type plugins to copy from.
   */
  public $handlers = array();

}
