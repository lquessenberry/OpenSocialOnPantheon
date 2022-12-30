<?php

namespace Drupal\ultimate_cron\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines a Launcher plugin annotation object.
 *
 * @Annotation
 *
 * @see \Drupal\ultimate_cron\LauncherManager
 */
class LauncherPlugin extends Plugin {

  /**
   * The plugin ID.
   *
   * @var string
   */
  public $id;

  /**
   * The human-readable title of the scheduler.
   *
   * @ingroup plugin_translatable
   *
   * @var \Drupal\Core\Annotation\Translation
   */
  public $title;

  /**
   * A short description of the scheduler.
   *
   * @ingroup plugin_translatable
   *
   * @var \Drupal\Core\Annotation\Translation
   */
  public $description;



  public $weight = 0;

}
