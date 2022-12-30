<?php

namespace Drupal\data_policy_export\Plugin;

use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\data_policy\Entity\DataPolicyInterface;

/**
 * Defines an interface for Data Policy export plugin plugins.
 */
interface DataPolicyExportPluginInterface extends PluginInspectionInterface {

  /**
   * Returns the header.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup|string
   *   The header.
   */
  public function getHeader();

  /**
   * Returns the value.
   *
   * @param \Drupal\data_policy\Entity\DataPolicyInterface $entity
   *   The Data Policy entity to get the value from.
   *
   * @return string
   *   The value.
   */
  public function getValue(DataPolicyInterface $entity);

}
