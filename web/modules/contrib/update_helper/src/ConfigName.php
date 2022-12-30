<?php

namespace Drupal\update_helper;

/**
 * Configuration name class for easier handling of configuration references.
 *
 * @package Drupal\update_helper
 */
class ConfigName {

  const SYSTEM_SIMPLE_CONFIG = 'system.simple';

  /**
   * Config type.
   *
   * @var string
   */
  protected $type;

  /**
   * Config name.
   *
   * @var string
   */
  protected $name;

  /**
   * Entity manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Create ConfigName instance from full configuration name.
   *
   * @param string $full_config_name
   *   Full config name.
   *
   * @return ConfigName
   *   Return instance of ConfigName.
   */
  public static function createByFullName($full_config_name) {
    $config_name = new static();

    $configPair = $config_name->parseFullName($full_config_name);

    $config_name->type = $configPair['type'];
    $config_name->name = $configPair['name'];

    return $config_name;
  }

  /**
   * Create ConfigName instance from configuration type and name.
   *
   * @param string $config_type
   *   Config type.
   * @param string $config_name
   *   Config name.
   *
   * @return ConfigName
   *   Return instance of ConfigName.
   */
  public static function createByTypeName($config_type, $config_name) {
    $config_name_instance = new static();

    $config_name_instance->type = $config_type;
    $config_name_instance->name = $config_name;

    return $config_name_instance;
  }

  /**
   * Parse full config name and create array with config type and name.
   *
   * @param string $full_config_name
   *   Full config name.
   *
   * @return array
   *   Returns array with config type and name.
   */
  protected function parseFullName($full_config_name) {
    $result = [
      'type' => static::SYSTEM_SIMPLE_CONFIG,
      'name' => $full_config_name,
    ];

    $prefix = static::SYSTEM_SIMPLE_CONFIG . '.';
    if (strpos($full_config_name, $prefix)) {
      $result['name'] = substr($full_config_name, strlen($prefix));
    }
    else {
      foreach ($this->entityTypeManager()->getDefinitions() as $entityType => $definition) {
        if ($definition->entityClassImplements('Drupal\Core\Config\Entity\ConfigEntityInterface')) {
          $prefix = $definition->getConfigPrefix() . '.';
          if (strpos($full_config_name, $prefix) === 0) {
            $result['type'] = $entityType;
            $result['name'] = substr($full_config_name, strlen($prefix));
          }
        }
      }
    }

    return $result;
  }

  /**
   * Create full configuration name from config type and name.
   *
   * @param string $type
   *   Config type.
   * @param string $name
   *   Config name.
   *
   * @return string
   *   Returns full configuration name.
   */
  protected function generateFullName($type, $name) {
    if ($type == 'system.simple' || !$type) {
      return $name;
    }

    $definition = $this->entityTypeManager()->getDefinition($type);
    $prefix = $definition->getConfigPrefix() . '.';

    return $prefix . $name;
  }

  /**
   * Retrieves the entity manager service.
   *
   * @return \Drupal\Core\Entity\EntityTypeManagerInterface
   *   The entity manager service.
   */
  protected function entityTypeManager() {
    if (!$this->entityTypeManager) {
      $this->entityTypeManager = \Drupal::service('entity_type.manager');
    }

    return $this->entityTypeManager;
  }

  /**
   * Get full configuration name.
   *
   * @return string
   *   Returns full configuration name.
   */
  public function getFullName() {
    return $this->generateFullName($this->type, $this->name);
  }

  /**
   * Get configuration type.
   *
   * @return string
   *   Config type.
   */
  public function getType() {
    return $this->type;
  }

  /**
   * Get configuration name.
   *
   * @return string
   *   Config name.
   */
  public function getName() {
    return $this->name;
  }

}
