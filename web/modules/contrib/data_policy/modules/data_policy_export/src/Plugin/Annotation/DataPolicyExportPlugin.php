<?php

namespace Drupal\data_policy_export\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines a Data Policy export plugin item annotation object.
 *
 * @see \Drupal\data_policy_export\Plugin\DataPolicyExportPluginManager
 * @see plugin_api
 *
 * @Annotation
 */
class DataPolicyExportPlugin extends Plugin {

  /**
   * The plugin ID.
   *
   * @var string
   */
  public $id;

  /**
   * The label of the plugin.
   *
   * @var \Drupal\Core\Annotation\Translation
   *
   * @ingroup plugin_translatable
   */
  public $label;

  /**
   * The plugin weight.
   *
   * @var int
   */
  public $weight;

}
