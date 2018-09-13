<?php

namespace Drupal\social_auth\Event;

/**
 * Defines Social Auth Events constants.
 */
final class SocialAuthEvents {
  /**
   * Name of the event fired when social auth is gathering user fields.
   *
   * Fired before a new user is created when gathering fields.
   *
   * @Event
   *
   * @see \Drupal\social_auth\Event\SocialAuthUserFieldsEvent
   *
   * @var string
   */
  const USER_FIELDS = 'social_auth.user.fields';

  /**
   * Name of the event fired when a new user is created via social auth.
   *
   * Fired after a new user account has been created.
   *
   * @Event
   *
   * @see \Drupal\social_auth\Event\SocialAuthUserEvent
   *
   * @var string
   */
  const USER_CREATED = 'social_auth.user.created';

  /**
   * Name of the event fired when a new user login using social auth.
   *
   * Fired after a user has logged in.
   *
   * @Event
   *
   * @see \Drupal\social_auth\Event\SocialAuthUserEvent
   *
   * @var string
   */
  const USER_LOGIN = 'social_auth.user.login';

}
