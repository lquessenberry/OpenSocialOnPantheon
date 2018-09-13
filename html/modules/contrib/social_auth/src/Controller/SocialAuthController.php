<?php

namespace Drupal\social_auth\Controller;

use Drupal\social_api\Controller\SocialApiController;

/**
 * Manages login buttons settings and integration table renderization.
 */
class SocialAuthController extends SocialApiController {

  /**
   * {@inheritdoc}
   */
  public function integrations($type = 'social_auth') {
    return parent::integrations($type);
  }

  /**
   * Sets the settings for the login button for the given social networking.
   *
   * @param string $module
   *   The module machine name.
   * @param string $route
   *   The route name of the user authentication controller.
   * @param string $img_path
   *   The path of the image for login.
   */
  public static function setLoginButtonSettings($module, $route, $img_path) {
    $config = \Drupal::configFactory()->getEditable('social_auth.settings');

    $img_path = drupal_get_path('module', $module) . '/' . $img_path;

    $config->set('auth.' . $module . '.route', $route)
      ->set('auth.' . $module . '.img_path', $img_path)
      ->save();
  }

  /**
   * Delete the settings for the login button for the given social networking.
   *
   * @param string $module
   *   The module machine name.
   */
  public static function deleteLoginButtonSettings($module) {
    $config = \Drupal::configFactory()->getEditable('social_auth.settings');;

    $config->clear('auth.' . $module)
      ->save();
  }

}
