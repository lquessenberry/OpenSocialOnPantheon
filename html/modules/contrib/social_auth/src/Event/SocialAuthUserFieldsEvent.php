<?php

namespace Drupal\social_auth\Event;

use Symfony\Component\EventDispatcher\Event;

/**
 * Defines the user fields event.
 *
 * @todo validate user_fields to be set
 *
 * @see \Drupal\social_auth\Event\SocialAuthEvents
 */
class SocialAuthUserFieldsEvent extends Event {

  /**
   * The user fields.
   *
   * @var array
   */
  protected $userFields;

  /**
   * The plugin id dispatching this event.
   *
   * @var string
   */
  protected $pluginId;

  /**
   * Constructs a new SocialAuthUserFieldsEvent.
   *
   * @param array $user_fields
   *   Initial user fields to populate the newly created user.
   * @param string $plugin_id
   *   The plugin Id dispatching this event.
   */
  public function __construct(array $user_fields, $plugin_id) {
    $this->userFields = $user_fields;
    $this->pluginId = $plugin_id;
  }

  /**
   * Gets the user fields.
   *
   * @return array
   *   Fields to initialize for the user creation.
   */
  public function getUserFields() {
    return $this->userFields;
  }

  /**
   * Sets the user fields.
   *
   * @param array $user_fields
   *   The user fields.
   */
  public function setUserFields(array $user_fields) {
    $this->userFields = $user_fields;
  }

  /**
   * Gets the plugin id dispatching this event.
   *
   * @return string
   *   The plugin id.
   */
  public function getPluginId() {
    return $this->pluginId;
  }

}
