<?php

namespace Drupal\update_helper;

use Drupal\Component\Serialization\SerializationInterface;
use Drupal\config_update\ConfigDiffInterface;
use Drupal\config_update\ConfigListInterface;
use Drupal\config_update\ConfigRevertInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Configuration handler.
 *
 * TODO: Create UpdateDefinition class to handle configuration update entry.
 *
 * @package Drupal\update_helper
 */
class ConfigHandler {

  use StringTranslationTrait;

  /**
   * The config lister.
   *
   * @var \Drupal\config_update\ConfigListInterface
   */
  protected $configList;

  /**
   * The config reverter.
   *
   * @var \Drupal\config_update\ConfigRevertInterface
   */
  protected $configReverter;

  /**
   * The config differ.
   *
   * @var ReversibleConfigDiffer
   */
  protected $configDiffer;

  /**
   * Config diff transformer service.
   *
   * @var \Drupal\update_helper\ConfigDiffTransformer
   */
  protected $configDiffTransformer;

  /**
   * Module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The serializer.
   *
   * @var \Drupal\Component\Serialization\SerializationInterface
   */
  protected $serializer;

  /**
   * Configuration exporter.
   *
   * @var \Drupal\update_helper\ConfigExporter
   */
  protected $configExporter;

  /**
   * List of configuration parameters that will be stripped out.
   *
   * @var array
   */
  protected $stripConfigParams = ['dependencies'];

  /**
   * Default path for configuration update files.
   *
   * @var string
   */
  protected $baseUpdatePath = '/config/update';

  /**
   * Config handler constructor.
   *
   * @param \Drupal\config_update\ConfigListInterface $config_list
   *   Config list service.
   * @param \Drupal\config_update\ConfigRevertInterface $config_reverter
   *   Config reverter service.
   * @param \Drupal\config_update\ConfigDiffInterface $config_differ
   *   Config differ service.
   * @param \Drupal\update_helper\ConfigDiffTransformer $config_diff_transformer
   *   Configuration transformer for diffing.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   Module handler service.
   * @param \Drupal\Component\Serialization\SerializationInterface $serializer
   *   Array serializer service.
   * @param \Drupal\update_helper\ConfigExporter $config_exporter
   *   Configuration exporter service.
   */
  public function __construct(ConfigListInterface $config_list, ConfigRevertInterface $config_reverter, ConfigDiffInterface $config_differ, ConfigDiffTransformer $config_diff_transformer, ModuleHandlerInterface $module_handler, SerializationInterface $serializer, ConfigExporter $config_exporter) {
    $this->configList = $config_list;
    $this->configReverter = $config_reverter;
    $this->configDiffer = $config_differ;
    $this->configDiffTransformer = $config_diff_transformer;
    $this->moduleHandler = $module_handler;
    $this->serializer = $serializer;
    $this->configExporter = $config_exporter;
  }

  /**
   * Generate patch from changed configuration.
   *
   * It compares Base vs. Active configuration and creates patch with defined
   * name in patch folder.
   *
   * @param string[] $module_names
   *   Module name that will be used to generate patch for it.
   * @param bool $from_active
   *   Flag if configuration should be updated from active to file configs.
   *
   * @return string|bool
   *   Rendering generated patch file name or FALSE if patch is empty.
   */
  public function generatePatchFile(array $module_names, $from_active) {
    $update_patch = [];

    foreach ($module_names as $module_name) {
      $configuration_lists = $this->configList->listConfig('module', $module_name);

      // Get required and optional configuration names.
      $module_config_names = array_merge($configuration_lists[1], $configuration_lists[2]);

      $config_names = $this->getConfigNames(array_intersect($module_config_names, $configuration_lists[0]));
      foreach ($config_names as $config_name) {
        $config_diff = $this->getConfigDiff($config_name, $from_active);
        $config_diff = $this->filterDiff($config_diff);
        if (!empty($config_diff)) {
          $update_patch[$config_name->getFullName()] = [
            'expected_config' => $this->getExpectedConfig($config_diff),
            'update_actions' => $this->getUpdateConfig($config_diff),
          ];
        }
      }
    }

    // We don't want to export configuration files in case we are making update
    // front active configuration to configuration provided in files.
    if (!$from_active) {
      $this->exportConfigurations(array_keys($update_patch));
    }

    return $update_patch ? $this->serializer::encode($update_patch) : FALSE;
  }

  /**
   * Export new configurations for modules.
   *
   * @param array $configuration_list
   *   List of configurations to export.
   */
  protected function exportConfigurations(array $configuration_list) {
    foreach ($configuration_list as $configuration_name) {
      $config_name = ConfigName::createByFullName($configuration_name);

      $config_data = $this->configDiffer->stripIgnore($this->configReverter->getFromActive($config_name->getType(), $config_name->getName()));
      $this->configExporter->exportConfiguration($config_name, $config_data);
    }
  }

  /**
   * Get diff for single configuration.
   *
   * @param \Drupal\update_helper\ConfigName $config_name
   *   Configuration name.
   * @param bool $from_active
   *   Flag if configuration should be updated from active to file configs.
   *
   * @return \Drupal\Component\Diff\Engine\DiffOp[]
   *   Return diff edits.
   */
  protected function getConfigDiff(ConfigName $config_name, $from_active) {
    $active_config = $this->getConfigFrom(
      $this->configReverter->getFromActive($config_name->getType(), $config_name->getName())
    );

    $file_config = $this->getConfigFrom(
      $this->configReverter->getFromExtension($config_name->getType(), $config_name->getName())
    );

    if (!$this->configDiffer->same($file_config, $active_config)) {
      if ($from_active) {
        $update_diff = $this->configDiffer->diff(
          $active_config,
          $file_config
        );
      }
      else {
        $update_diff = $this->configDiffer->diff(
          $file_config,
          $active_config
        );
      }

      /** @var \Drupal\Component\Diff\Engine\DiffOp[] $edits */
      return $update_diff->getEdits();
    }

    return [];
  }

  /**
   * Filter diffs that are not relevant, where configuration is equal.
   *
   * @param array $diffs
   *   List of diff edits.
   *
   * @return array
   *   Return list of filtered diffs.
   */
  protected function filterDiff(array $diffs) {
    return array_filter(
      $diffs,
      function ($diffOp) {
        return $diffOp->type != 'copy';
      }
    );
  }

  /**
   * Get list of expected configuration on not updated system.
   *
   * @param array $diffs
   *   List of diff edits.
   *
   * @return array
   *   Return configuration array that is expected on old system.
   */
  protected function getExpectedConfig(array $diffs) {
    $list_expected = [];

    foreach ($diffs as $diff_op) {
      if (!empty($diff_op->orig)) {
        $list_expected = array_merge($list_expected, $diff_op->orig);
      }
    }

    return $this->configDiffTransformer->reverseTransform($list_expected);
  }

  /**
   * Get list of configuration changes with change action (add, delete, change).
   *
   * @param array $diffs
   *   List of diff edits.
   *
   * @return array
   *   Return configuration array that should be applied.
   */
  protected function getUpdateConfig(array $diffs) {
    $list_update = [
      'delete' => [],
      'add' => [],
      'change' => [],
    ];

    foreach ($diffs as $diff_op) {
      if (!empty($diff_op->closing)) {
        if ($diff_op->type === 'change') {
          $removable_edits = $this->getRemovableEdits($diff_op->orig, $diff_op->closing);
          if (!empty($removable_edits)) {
            $list_update['delete'] = array_merge($list_update['delete'], $removable_edits);
          }
        }

        $list_update[$diff_op->type] = array_merge($list_update[$diff_op->type], $diff_op->closing);
      }
      elseif ($diff_op->type === 'delete' && !empty($diff_op->orig)) {
        $list_update[$diff_op->type] = array_merge($list_update[$diff_op->type], $diff_op->orig);
      }
    }

    $list_update = array_filter($list_update);
    foreach ($list_update as $action => $edits) {
      $list_update[$action] = $this->configDiffTransformer->reverseTransform($edits);
    }

    return $list_update;
  }

  /**
   * Get edits that should be removed before applying change action.
   *
   * @param array $original_edits
   *   Original list of edits for compare.
   * @param array $new_edits
   *   New list of edits for compare.
   *
   * @return array
   *   Returns list of edits that should be removed.
   */
  protected function getRemovableEdits(array $original_edits, array $new_edits) {
    $additional_edits = array_udiff($original_edits, $new_edits, function ($diff_row1, $diff_row2) {
      $key1 = explode(' : ', $diff_row1);
      $key2 = explode(' : ', $diff_row2);

      // Values from flat array will be marked for removal.
      if (substr($key1[0], -3) === '::-' && substr($key2[0], -3) === '::-') {
        return -1;
      }

      return strcmp($key1[0], $key2[0]);
    });

    return $additional_edits;
  }

  /**
   * Ensure that configuration is always array and cleaned up.
   *
   * Not needed configuration parameters will be stripped.
   *
   * @param mixed $config_data
   *   Configuration data that should be checked.
   *
   * @return array
   *   Returns configuration data array if it's not empty configuration,
   *   otherwise returns empty array.
   */
  protected function getConfigFrom($config_data) {
    if (empty($config_data)) {
      return [];
    }

    // Strip params that are not needed.
    foreach ($this->stripConfigParams as $param) {
      if (isset($config_data[$param])) {
        unset($config_data[$param]);
      }
    }

    return $config_data;
  }

  /**
   * Get list of ConfigName instances from list of config names.
   *
   * @param array $config_list
   *   List of config names (string).
   *
   * @return array
   *   List of ConfigName instances crated from string config name.
   */
  protected function getConfigNames(array $config_list) {
    $config_names = [];
    foreach ($config_list as $config_file) {
      $config_names[] = ConfigName::createByFullName($config_file);
    }

    return $config_names;
  }

  /**
   * Get full path for update patch file.
   *
   * @param string $module_name
   *   Module name.
   * @param string $update_name
   *   Update name.
   * @param bool $create_directory
   *   Flag if directory should be created.
   *
   * @return string
   *   Returns full path file name for update patch.
   */
  public function getPatchFile($module_name, $update_name, $create_directory = FALSE) {
    $update_dir = $this->moduleHandler->getModule($module_name)->getPath() . $this->baseUpdatePath;

    // Ensure that directory exists.
    if (!is_dir($update_dir) && $create_directory) {
      mkdir($update_dir, 0755, TRUE);
    }

    return $update_dir . '/' . $update_name . '.' . $this->serializer->getFileExtension();
  }

  /**
   * Load update definition from file.
   *
   * @param string $module_name
   *   Module name.
   * @param string $update_name
   *   Update name.
   *
   * @return mixed
   *   Returns update definition.
   */
  public function loadUpdate($module_name, $update_name) {
    return $this->serializer->decode(file_get_contents($this->getPatchFile($module_name, $update_name)));
  }

}
