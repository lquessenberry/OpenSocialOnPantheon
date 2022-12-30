<?php

namespace Drupal\bootstrap\Plugin;

use Drupal\bootstrap\Theme;
use Drupal\bootstrap\Utility\SortArray;

/**
 * Manages discovery and instantiation of Bootstrap theme settings.
 *
 * @ingroup plugins_setting
 */
class SettingManager extends PluginManager {

  /**
   * Provides the order of top-level groups.
   *
   * @var string[]
   */
  protected static $groupOrder = [
    'general',
    'components',
    'javascript',
    'cdn',
    'advanced',
  ];

  /**
   * Constructs a new \Drupal\bootstrap\Plugin\SettingManager object.
   *
   * @param \Drupal\bootstrap\Theme $theme
   *   The theme to use for discovery.
   */
  public function __construct(Theme $theme) {
    parent::__construct($theme, 'Plugin/Setting', 'Drupal\bootstrap\Plugin\Setting\SettingInterface', 'Drupal\bootstrap\Annotation\BootstrapSetting');
    $this->setCacheBackend(\Drupal::cache('discovery'), 'theme:' . $theme->getName() . ':setting', $this->getCacheTags());
  }

  /**
   * {@inheritdoc}
   */
  protected function sortDefinitions(array &$definitions) {
    uasort($definitions, [$this, 'sort']);
  }

  /**
   * Sorts the setting plugin definitions.
   *
   * Sorts setting plugin definitions in the following order:
   * - First by top level group.
   * - Then by sub-groups.
   * - Then by weight.
   * - Then by identifier.
   *
   * @param array $a
   *   First plugin definition for comparison.
   * @param array $b
   *   Second plugin definition for comparison.
   *
   * @return int
   *   The comparison result.
   */
  public static function sort(array $a, array $b) {
    $aIndex = static::getTopLevelGroupIndex($a);
    $bIndex = static::getTopLevelGroupIndex($b);

    // Top level group isn't the same, sort by index.
    if ($aIndex !== $bIndex) {
      return $aIndex - $bIndex;
    }

    // Next sort by all groups (sub-groups).
    $result = SortArray::sortByKeyString($a, $b, 'groups');

    // Groups are the same.
    if ($result === 0) {
      // Sort by weight.
      $result = SortArray::sortByWeightElement($a, $b);

      // Weights are the same.
      if ($result === 0) {
        // Sort by plugin identifier.
        $result = SortArray::sortByKeyString($a, $b, 'id');
      }
    }

    return $result;
  }

  /**
   * Retrieves the index of the top level group.
   *
   * @param array $definition
   *   A plugin definition.
   *
   * @return int
   *   The array index of the top level group.
   */
  public static function getTopLevelGroupIndex(array $definition) {
    $groups = !empty($definition['groups']) ? array_keys($definition['groups']) : [];
    $index = array_search(reset($groups), static::$groupOrder);
    if ($index === FALSE) {
      $index = -1;
    }
    return $index;
  }

}
