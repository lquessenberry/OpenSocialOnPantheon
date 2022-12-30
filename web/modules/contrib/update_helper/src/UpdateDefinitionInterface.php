<?php

namespace Drupal\update_helper;

/**
 * The update definition represents update actions provided in YML file.
 *
 * @package Drupal\update_helper
 */
interface UpdateDefinitionInterface {

  /**
   * Special CUD key used by update helper to make global system changes.
   *
   * Current provided global actions are "install_modules" and "import_configs".
   */
  const GLOBAL_ACTIONS = '__global_actions';

  /**
   * Global action key for installing modules.
   */
  const GLOBAL_ACTION_INSTALL_MODULES = 'install_modules';

  /**
   * Global action key for installing themes.
   */
  const GLOBAL_ACTION_INSTALL_THEMES = 'install_themes';

  /**
   * Global action key for importing configurations.
   */
  const GLOBAL_ACTION_IMPORT_CONFIGS = 'import_configs';

}
