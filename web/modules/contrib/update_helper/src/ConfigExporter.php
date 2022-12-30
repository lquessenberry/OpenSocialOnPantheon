<?php

namespace Drupal\update_helper;

use Drupal\Component\Serialization\SerializationInterface;
use Drupal\Core\Config\StorageInterface;

/**
 * Configuration exporter service.
 *
 * TODO:
 *  - Add support for create new.
 *
 * @package Drupal\update_helper
 */
class ConfigExporter {

  /**
   * The extension config storage for config/install config items.
   *
   * @var \Drupal\Core\Config\FileStorage
   */
  protected $extensionConfigStorage;

  /**
   * The extension config storage for config/optional config items.
   *
   * @var \Drupal\Core\Config\FileStorage
   */
  protected $extensionOptionalConfigStorage;

  /**
   * Yaml serializer.
   *
   * @var \Drupal\Component\Serialization\SerializationInterface
   */
  protected $serializer;

  /**
   * ConfigExporter constructor.
   *
   * @param \Drupal\Core\Config\StorageInterface $extension_config_storage
   *   The extension config storage.
   * @param \Drupal\Core\Config\StorageInterface $extension_optional_config_storage
   *   The extension config storage for optional config items.
   * @param \Drupal\Component\Serialization\SerializationInterface $serializer
   *   Array serializer service.
   */
  public function __construct(StorageInterface $extension_config_storage, StorageInterface $extension_optional_config_storage, SerializationInterface $serializer) {
    $this->extensionConfigStorage = $extension_config_storage;
    $this->extensionOptionalConfigStorage = $extension_optional_config_storage;
    $this->serializer = $serializer;
  }

  /**
   * Export configuration.
   *
   * TODO: It would be good to log that file has been changed!!!
   *
   * @param \Drupal\update_helper\ConfigName $config_name
   *   Config name.
   * @param array $data
   *   Configuration array.
   *
   * @return bool
   *   Returns if configuration is stored.
   */
  public function exportConfiguration(ConfigName $config_name, array $data) {
    $config_full_name = $config_name->getFullName();

    // Check if file with provided config name exists in install or optional
    // config and update it accordingly.
    foreach ([$this->extensionConfigStorage, $this->extensionOptionalConfigStorage] as $config_storage) {
      $file_data = $config_storage->read($config_full_name);
      if ($file_data) {
        $full_file_path = $config_storage->getFilePath($config_full_name);

        return file_put_contents($full_file_path, $this->serializer::encode($data)) !== FALSE;
      }
    }

    return FALSE;
  }

}
