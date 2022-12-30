#!/usr/bin/env php
<?php

/**
 * @file
 * Generates the markdown documentation for all available theme settings.
 */

/**
 * Note: this script is intended to be executed independently via PHP, e.g.:
 * $ ./scripts/gen-theme-setting-docs.php
 */

use Drupal\bootstrap\Bootstrap;
use Drupal\bootstrap\Plugin\Setting\DeprecatedSettingInterface;
use Drupal\bootstrap\Plugin\Setting\SettingInterface;
use Drupal\Component\Render\FormattableMarkup;
use Drupal\Component\Utility\Html;
use Drupal\Core\Serialization\Yaml;

$kernel = require_once __DIR__ . '/bootstrap.php';

$bootstrap = Bootstrap::getTheme('bootstrap');

/** @var \Drupal\bootstrap\Plugin\Setting\SettingInterface[] $settings */
$settings = array_filter($bootstrap->getSettingPlugin(NULL, TRUE), function (SettingInterface $setting) {
  return !!$setting->getGroups();
});

// Populate the variables with settings.
$variables = ['groups' => []];
$deprecatedSettings = [];
$replacementPairs = [
  '&quot;' => '"',
  '\n' => "\n",
];
foreach ($settings as $id => $setting) {
  $defaultValue = $setting->getDefaultValue();
  $deprecated = FALSE;
  if ($setting instanceof DeprecatedSettingInterface) {
    $newSetting = $setting->getDeprecatedReplacementSetting()->getPluginId();
    $deprecated = [
      'reason' => new FormattableMarkup($setting->getDeprecatedReason(), []),
      'replacement' => new FormattableMarkup('<a href="#@anchor">@setting</a>', [
        '@anchor' => Html::cleanCssIdentifier($newSetting),
        '@setting' => $newSetting,
      ]),
      'version' => new FormattableMarkup($setting->getDeprecatedVersion(), []),
    ];
  }
  $data = [
    'id' => $id,
    'description' => new FormattableMarkup(strtr($setting->getDescription(), $replacementPairs), []),
    'defaultValue' => $defaultValue !== NULL ? new FormattableMarkup(strtr(trim(Yaml::encode([$id => $defaultValue])), $replacementPairs), []) : NULL,
    'deprecated' => $deprecated,
  ];

  // Defer adding deprecated settings.
  if ($deprecated) {
    $deprecatedSettings[$id] = $data;
  }
  else {
    // Only get the first two groups (we don't need 3rd, or more, levels).
    $header = implode(' > ', array_slice(array_filter($setting->getGroups()), 0, 2, FALSE));
    $variables['groups'][$header][$id] = $data;
  }
}

// Add Deprecated settings last (special table).
if ($deprecatedSettings) {
  $variables['deprecated'] = $deprecatedSettings;
}

$docsPath = "{$bootstrap->getPath()}/docs";

// Render the settings.
$output = Bootstrap::renderCustomTemplate("{$docsPath}/theme-settings.twig", $variables);

// Save the generated output to the appropriate file.
$result = Bootstrap::putContents("{$docsPath}/Theme-Settings.md", $output, '<!-- THEME SETTINGS GENERATION START -->', '<!-- THEME SETTINGS GENERATION END -->');

if ($result) {
  echo 'Successfully generated theme documentation!';
  exit(0);
}

echo 'Unable to generate theme documentation!';
exit(1);
