<?php

/**
 * @file
 * Locates the Drupal root directory and bootstraps the kernel.
 */

use Drupal\Core\DrupalKernel;
use Symfony\Component\HttpFoundation\Request;

// Immediately return if classes are discoverable (already booted).
if (class_exists('\Drupal\Core\DrupalKernel') && class_exists('\Drupal')) {
  return \Drupal::service('kernel');
}

/**
 *
 */
function _find_autoloader($dir) {
  if (file_exists($autoloadFile = $dir . '/autoload.php') || file_exists($autoloadFile = $dir . '/vendor/autoload.php')) {
    return include_once $autoloadFile;
  }
  elseif (empty($dir) || $dir === DIRECTORY_SEPARATOR) {
    return FALSE;
  }
  return _find_autoloader(dirname($dir));
}

$autoloader = _find_autoloader(empty($_SERVER['PWD']) ? getcwd() : $_SERVER['PWD']);
if (!$autoloader || !class_exists('\Drupal\Core\DrupalKernel')) {
  print "This script must be invoked inside a Drupal 8 environment. Unable to continue.\n";
  exit();
}

// Create a DrupalKernel instance.
DrupalKernel::bootEnvironment();
$kernel = new DrupalKernel('prod', $autoloader);

// Need to change the current working directory to the actual root path.
// This is needed in case the script is initiated inside a sub-directory.
chdir($kernel->getAppRoot());

// Initialize settings, this requires reflection since its a protected method.
$request = Request::createFromGlobals();
$initializeSettings = new \ReflectionMethod($kernel, 'initializeSettings');
$initializeSettings->setAccessible(TRUE);
$initializeSettings->invokeArgs($kernel, [$request]);

// Boot the kernel.
$kernel->boot();
$kernel->preHandle($request);

// Due to a core bug, the theme handler has to be invoked to register theme
// namespaces with the autoloader.
// @todo Remove once installed_extensions makes its way into core.
// @see https://www.drupal.org/project/drupal/issues/2941757
$container = $kernel->getContainer();
if (!$container->has('installed_extensions')) {
  $container->get('theme_handler')->listInfo();
}

return $kernel;
