<?php

namespace Drupal\social_auth;

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\Core\Url;
use Drupal\Component\Utility\Unicode;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Routing\RouteProviderInterface;
use Drupal\Core\Routing\UrlGeneratorTrait;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Transliteration\PhpTransliteration;
use Drupal\Core\Utility\Token;
use Drupal\social_auth\Event\SocialAuthEvents;
use Drupal\social_auth\Event\SocialAuthUserEvent;
use Drupal\social_auth\Event\SocialAuthUserFieldsEvent;
use Drupal\user\Entity\User;
use Drupal\user\UserInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

/**
 * Contains all logic that is related to Drupal user management.
 */
class SocialAuthUserManager {
  use UrlGeneratorTrait;
  use StringTranslationTrait;

  protected $configFactory;
  protected $loggerFactory;
  protected $eventDispatcher;
  protected $entityTypeManager;
  protected $entityFieldManager;
  protected $token;
  protected $transliteration;
  protected $languageManager;
  protected $routeProvider;

  /**
   * The implementer plugin id.
   *
   * @var string
   */
  protected $pluginId;

  /**
   * The session manager.
   *
   * @var \Symfony\Component\HttpFoundation\Session\SessionInterface
   */
  protected $session;

  /**
   * Session keys to nullify is user could not be logged in.
   *
   * @var array
   */
  protected $sessionKeys;

  /**
   * Constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   Used for accessing Drupal configuration.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   Used for logging errors.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher
   *   Used for dispatching social auth events.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Used for loading and creating Drupal user objects.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager
   *   Used for access Drupal user field definitions.
   * @param \Drupal\Core\Utility\Token $token
   *   Used for token support in Drupal user picture directory.
   * @param \Drupal\Core\Transliteration\PhpTransliteration $transliteration
   *   Used for user picture directory and file transliteration.
   * @param \Drupal\Core\Routing\RouteProviderInterface $route_provider
   *   Used to check if route path exists.
   * @param SessionInterface $session
   *   Used for reading data from and writing data to session.
   */
  public function __construct(ConfigFactoryInterface $config_factory, LoggerChannelFactoryInterface $logger_factory, EventDispatcherInterface $event_dispatcher, EntityTypeManagerInterface $entity_type_manager, EntityFieldManagerInterface $entity_field_manager, Token $token, PhpTransliteration $transliteration, LanguageManagerInterface $language_manager, RouteProviderInterface $route_provider, SessionInterface $session) {
    $this->configFactory      = $config_factory;
    $this->loggerFactory      = $logger_factory;
    $this->eventDispatcher    = $event_dispatcher;
    $this->entityTypeManager  = $entity_type_manager;
    $this->entityFieldManager = $entity_field_manager;
    $this->token              = $token;
    $this->transliteration    = $transliteration;
    $this->languageManager    = $language_manager;
    $this->routeProvider      = $route_provider;
    $this->session            = $session;
    // Sets default plugin id.
    $this->setPluginId('social_auth');
  }

  /**
   * Sets the implementer plugin id.
   *
   * This value is used to generate customized logs, drupal messages, and event
   * dispatchers.
   *
   * @param string $plugin_id
   *   The plugin id.
   */
  public function setPluginId($plugin_id) {
    $this->pluginId = $plugin_id;
  }

  /**
   * Gets the implementer plugin id.
   *
   * This value is used to generate customized logs, drupal messages, and events
   * dispatchers.
   *
   * @return string
   *   The plugin id.
   */
  public function getPluginId() {
    return $this->pluginId;
  }

  /**
   * Sets the session keys to nullify if user could not logged in.
   *
   * @param array $session_keys
   *   The session keys to nullify.
   */
  public function setSessionKeysToNullify(array $session_keys) {
    $this->sessionKeys = $session_keys;
  }

  /**
   * Creates and/or authenticates an user.
   *
   * @param string $email
   *   The user's email address.
   * @param string $name
   *   The user's name.
   * @param string $id
   *   The user's id in provider.
   * @param string|bool $picture_url
   *   The user's picture.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   A redirect response.
   */
  public function authenticateUser($email, $name, $id = NULL, $picture_url = FALSE) {
    // Tries to load the user by their email.
    $drupal_user = $this->loadUserByProperty('mail', $email);
    // If user email has already an account in the site.
    if ($drupal_user) {
      // Authenticates and redirect existing user.
      return $this->authenticateExistingUser($drupal_user);
    }

    $drupal_user = $this->createUser($name, $email);
    // If the new user could be registered.
    if ($drupal_user) {
      // Download profile picture for the newly created user.
      if ($picture_url) {
        $this->setProfilePic($drupal_user, $picture_url, $id);
      }
      // Authenticates and redirect new user.
      return $this->authenticateNewUser($drupal_user);
    }

    drupal_set_message($this->t('You could not be authenticated, please contact the administrator'), 'error');
    $this->nullifySessionKeys();
    return $this->redirect('user.login');
  }

  /**
   * Authenticates and redirects existing users in authentication process.
   *
   * @param \Drupal\user\UserInterface $drupal_user
   *   User object to authenticate.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   A redirect response.
   */
  public function authenticateExistingUser(UserInterface $drupal_user) {
    // If Admin (user 1) can not authenticate.
    if ($this->isAdminDisabled($drupal_user)) {
      $this->nullifySessionKeys();
      drupal_set_message($this->t('Authentication for Admin (user 1) is disabled.'), 'error');
      return $this->redirect('user.login');
    }

    // If user can not login because of their role.
    $disabled_role = $this->isUserRoleDisabled($drupal_user);
    if ($disabled_role) {
      drupal_set_message($this->t("Authentication for '@role' role is disabled.", array('@role' => $disabled_role)), 'error');
      return $this->redirect('user.login');
    }

    // If user could be logged in.
    if ($this->loginUser($drupal_user)) {
      return $this->getLoginPostPath();
    }
    else {
      $this->nullifySessionKeys();
      drupal_set_message($this->t("Your account has not been approved yet or might have been canceled, please contact the administrator"), 'error');
      return $this->redirect('user.login');
    }
  }

  /**
   * Authenticates and redirects new users in authentication process.
   *
   * @param \Drupal\user\UserInterface $drupal_user
   *   User object to login.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   A redirect response.
   */
  public function authenticateNewUser(UserInterface $drupal_user) {
    // If the account needs admin approval.
    if ($this->isApprovalRequired()) {
      drupal_set_message($this->t("Your account was created, but it needs administrator's approval"), 'warning');
      $this->nullifySessionKeys();
      return $this->redirect('user.login');
    }

    // If the new user could be logged in.
    if ($this->loginUser($drupal_user)) {
      // User form redirection or false if option is not enabled.
      $redirect = $this->redirectToUserForm($drupal_user);

      if ($redirect) {
        return $redirect;
      }
      else {
        return $this->getLoginPostPath();
      }
    }
  }

  /**
   * Loads existing Drupal user object by given property and value.
   *
   * Note that first matching user is returned. Email address and account name
   * are unique so there can be only zero or one matching user when
   * loading users by these properties.
   *
   * @param string $field
   *   User entity field to search from.
   * @param string $value
   *   Value to search for.
   *
   * @return \Drupal\user\Entity\User|false
   *   Drupal user account if found
   *   False otherwise
   */
  public function loadUserByProperty($field, $value) {
    $users = $this->entityTypeManager
      ->getStorage('user')
      ->loadByProperties(array($field => $value));

    if (!empty($users)) {
      return current($users);
    }

    // If user was not found, return FALSE.
    return FALSE;
  }

  /**
   * Create a new user account.
   *
   * @param string $name
   *   User's name on Provider.
   * @param string $email
   *   User's email address.
   *
   * @return \Drupal\user\Entity\User|false
   *   Drupal user account if user was created
   *   False otherwise
   */
  public function createUser($name, $email) {
    // Make sure we have everything we need.
    if (!$name || !$email) {
      $this->loggerFactory
        ->get($this->getPluginId())
        ->error('Failed to create user. Name: @name, email: @email', array('@name' => $name, '@email' => $email));
      return FALSE;
    }

    // Check if site configuration allows new users to register.
    if ($this->registrationBlocked()) {
      $this->loggerFactory
        ->get($this->getPluginId())
        ->warning('Failed to create user. User registration is disabled in Drupal account settings. Name: @name, email: @email.', array('@name' => $name, '@email' => $email));

      drupal_set_message($this->t('User registration is disabled, please contact the administrator.'), 'error');

      return FALSE;
    }

    // Get the current UI language.
    $langcode = $this->languageManager->getCurrentLanguage()->getId();

    // Initializes the user fields.
    $fields = $this->getUserFields($name, $email, $langcode);

    // Create new user account.
    /** @var \Drupal\user\Entity\User $new_user */
    $new_user = $this->entityTypeManager
      ->getStorage('user')
      ->create($fields);

    // Try to save the new user account.
    try {
      $new_user->save();

      $this->loggerFactory
        ->get($this->getPluginId())
        ->notice('New user created. Username @username, UID: @uid', [
          '@username' => $new_user->getAccountName(),
          '@uid' => $new_user->id(),
        ]);

      // Dipatches SocialAuthEvents::USER_CREATED event.
      $event = new SocialAuthUserEvent($new_user, $this->getPluginId());
      $this->eventDispatcher->dispatch(SocialAuthEvents::USER_CREATED, $event);

      return $new_user;
    }
    catch (EntityStorageException $ex) {
      $this->loggerFactory
        ->get($this->getPluginId())
        ->error('Could not create new user. Exception: @message', ['@message' => $ex->getMessage()]);
    }

    return FALSE;
  }

  /**
   * Logs the user in.
   *
   * @param \Drupal\user\Entity\User $drupal_user
   *   User object.
   *
   * @return bool
   *   True if login was successful
   *   False if the login was blocked
   */
  public function loginUser(User $drupal_user) {
    // Check that the account is active and log the user in.
    if ($drupal_user->isActive()) {
      $this->userLoginFinalize($drupal_user);

      // Dipatches SocialAuthEvents::USER_LOGIN event.
      $event = new SocialAuthUserEvent($drupal_user, $this->getPluginId());
      $this->eventDispatcher->dispatch(SocialAuthEvents::USER_LOGIN, $event);

      return TRUE;
    }

    $this->loggerFactory
      ->get($this->getPluginId())
      ->warning('Login for user @user prevented. Account is blocked.', array('@user' => $drupal_user->getAccountName()));

    return FALSE;
  }

  /**
   * Nullifies session keys if user could not logged in.
   */
  protected function nullifySessionKeys() {
    if (!empty($this->sessionKeys)) {
      array_walk($this->sessionKeys, function ($session_key) {
        $this->session->set($session_key, NULL);
      });
    }
  }

  /**
   * Checks if user registration is blocked in Drupal account settings.
   *
   * @return bool
   *   True if registration is blocked
   *   False if registration is not blocked
   */
  protected function registrationBlocked() {
    // Check if Drupal account registration settings is Administrators only.
    if ($this->configFactory->get('user.settings')->get('register') == 'admin_only') {
      return TRUE;
    }

    return FALSE;
  }

  /**
   * Checks if admin approval is required for new users.
   *
   * @return bool
   *   True if approval is required
   *   False if approval is not required
   */
  protected function isApprovalRequired() {
    if ($this->configFactory->get('user.settings')->get('register') == 'visitors_admin_approval') {
      return TRUE;
    }

    return FALSE;
  }

  /**
   * Checks if Admin (user 1) can login.
   *
   * @param \Drupal\user\UserInterface $drupal_user
   *   User object to check if user is admin.
   *
   * @return bool
   *   True if user 1 can't login.
   *   False otherwise
   */
  protected function isAdminDisabled(UserInterface $drupal_user) {
    if ($this->configFactory->get('social_auth.settings')->get('disable_admin_login')
      && $drupal_user->id() == 1) {

      return TRUE;
    }

    return FALSE;
  }

  /**
   * Checks if User with specific roles is allowed to login.
   *
   * @param \Drupal\user\UserInterface $drupal_user
   *   User object to check if user has a specific role.
   *
   * @return string|false
   *   The role that can't login.
   *   False if the user roles are not disabled.
   */
  protected function isUserRoleDisabled(UserInterface $drupal_user) {
    foreach ($this->configFactory->get('social_auth.settings')->get('disabled_roles') as $role) {
      if ($drupal_user->hasRole($role)) {
        return $role;
      }
    }

    return FALSE;
  }

  /**
   * Returns the Post Login Path.
   *
   * @return string
   *   Post Login Path to which the user would be redirected after login.
   */
  protected function getLoginPostPath() {
    $post_login = $this->configFactory->get('social_auth.settings')->get('post_login');
    $routes = $this->routeProvider->getRoutesByNames(array($post_login));
    if (empty($routes)) {
      // Route does not exist so just redirect to path.
      return new RedirectResponse(Url::fromUserInput($post_login)->toString());
    }
    else {
      return $this->redirect($post_login);
    }
  }

  /**
   * Checks if User should be redirected to User Form after creation.
   *
   * @param \Drupal\user\UserInterface $drupal_user
   *   User object to get the id of user.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse|false
   *   A redirect response to user form, if option is enabled.
   *   False otherwise
   */
  protected function redirectToUserForm(UserInterface $drupal_user) {
    if ($this->configFactory->get('social_auth.settings')->get('redirect_user_form')) {
      return $this->redirect('entity.user.edit_form', [
        'user' => $drupal_user->id(),
      ]);
    }
    return FALSE;
  }

  /**
   * Ensures that Drupal usernames will be unique.
   *
   * Drupal usernames will be generated so that the user's full name on Provider
   * will become user's Drupal username. This method will check if the username
   * is already used and appends a number until it finds the first available
   * username.
   *
   * @param string $name
   *   User's full name on provider.
   *
   * @return string
   *   Unique drupal username.
   */
  protected function generateUniqueUsername($name) {
    $max_length = 60;
    $name = Unicode::substr($name, 0, $max_length);

    // Add a trailing number if needed to make username unique.
    $base = $name;
    $i = 1;
    $candidate = $base;
    while ($this->loadUserByProperty('name', $candidate)) {
      $i++;
      // Calculate max length for $base and truncate if needed.
      $max_length_base = $max_length - strlen((string) $i) - 1;
      $base = Unicode::substr($base, 0, $max_length_base);
      $candidate = $base . ' ' . $i;
    }

    // Trim leading and trailing whitespace.
    $candidate = trim($candidate);

    return $candidate;
  }

  /**
   * Returns the status for new users.
   *
   * @return int
   *   Value 0 means that new accounts remain blocked and require approval.
   *   Value 1 means that visitors can register new accounts without approval.
   */
  protected function getNewUserStatus() {
    if ($this->configFactory
      ->get('user.settings')
      ->get('register') == 'visitors') {
      return 1;
    }

    return 0;
  }

  /**
   * Downloads and sets user profile picture.
   *
   * @param User $drupal_user
   *   User object to update the profile picture for.
   * @param string $picture_url
   *   Absolute URL where the picture will be downloaded from.
   * @param string $id
   *   User's ID.
   *
   * @return bool
   *   True if picture was successfully set.
   *   False otherwise.
   */
  public function setProfilePic(User $drupal_user, $picture_url, $id) {
    // Try to download the profile picture and add it to user fields.
    if ($this->userPictureEnabled()) {
      if ($file = $this->downloadProfilePic($picture_url, $id)) {
        $drupal_user->set('user_picture', $file->id());
        $drupal_user->save();
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * Downloads the profile picture to Drupal filesystem.
   *
   * @param string $picture_url
   *   Absolute URL where to download the profile picture.
   * @param string $id
   *   Social network ID of the user.
   *
   * @return \Drupal\file\FileInterface|false
   *   FileInterface object if file was succesfully downloaded
   *   False otherwise
   */
  protected function downloadProfilePic($picture_url, $id) {
    // Make sure that we have everything we need.
    if (!$picture_url || !$id) {
      return FALSE;
    }

    // Determine target directory.
    $scheme = $this->configFactory->get('system.file')->get('default_scheme');
    $file_directory = $this->getPictureDirectory();

    if (!$file_directory) {
      return FALSE;
    }
    $directory = $scheme . '://' . $file_directory;

    // Replace tokens.
    $directory = $this->token->replace($directory);

    // Transliterate directory name.
    $directory = $this->transliteration->transliterate($directory, 'en', '_', 50);

    if (!$this->filePrepareDirectory($directory, 1)) {
      $this->loggerFactory
        ->get($this->getPluginId())
        ->error('Could not save @plugin_id\'s provider profile picture. Directory is not writable: @directory', [
          '@directory' => $directory,
          '@provider' => $this->getPluginId(),
        ]);
      return FALSE;
    }

    // Generate filename and transliterate.
    $filename = $this->transliteration->transliterate($this->getPluginId() . '_' . $id . '.jpg', 'en', '_', 50);

    $destination = $directory . DIRECTORY_SEPARATOR . $filename;

    // Download the picture to local filesystem.
    if (!$file = $this->systemRetrieveFile($picture_url, $destination, TRUE, 1)) {
      $this->loggerFactory
        ->get($this->getPluginId())
        ->error('Could not download @plugin_id\'s provider profile picture from url: @url', [
          '@url' => $picture_url,
          '@plugin_id' => $this->getPluginId(),
        ]);

      return FALSE;
    }

    return $file;
  }

  /**
   * Returns whether this site supports the default user picture feature.
   *
   * We use this method instead of the procedural user_pictures_enabled()
   * so that we can unit test our own methods.
   *
   * @return bool
   *   True if user pictures are enabled
   *   False otherwise
   */
  protected function userPictureEnabled() {
    $field_definitions = $this->entityFieldManager->getFieldDefinitions('user', 'user');
    return isset($field_definitions['user_picture']);
  }

  /**
   * Returns picture directory if site supports the user picture feature.
   *
   * @return string|bool
   *   Directory for user pictures if site supports user picture feature.
   *   False otherwise.
   */
  protected function getPictureDirectory() {
    $field_definitions = $this->entityFieldManager->getFieldDefinitions('user', 'user');
    if (isset($field_definitions['user_picture'])) {
      return $field_definitions['user_picture']->getSetting('file_directory');
    }
    return FALSE;
  }

  /**
   * Wrapper for file_prepare_directory.
   *
   * We need to wrap the legacy procedural Drupal API functions so that we are
   * not using them directly in our own methods. This way we can unit test our
   * own methods.
   *
   * @see file_prepare_directory
   */
  protected function filePrepareDirectory(&$directory, $options) {
    return file_prepare_directory($directory, $options);
  }

  /**
   * Wrapper for system_retrieve_file.
   *
   * We need to wrap the legacy procedural Drupal API functions so that we are
   * not using them directly in our own methods. This way we can unit test our
   * own methods.
   *
   * @see system_retrieve_file
   */
  protected function systemRetrieveFile($url, $destination, $managed, $replace) {
    return system_retrieve_file($url, $destination, $managed, $replace);
  }

  /**
   * Wrapper for user_password.
   *
   * We need to wrap the legacy procedural Drupal API functions so that we are
   * not using them directly in our own methods. This way we can unit test our
   * own methods.
   *
   * @param int $length
   *   Length of the password.
   *
   * @return string
   *   The password.
   *
   * @see user_password
   */
  protected function userPassword($length) {
    return user_password($length);
  }

  /**
   * Wrapper for user_login_finalize.
   *
   * We need to wrap the legacy procedural Drupal API functions so that we are
   * not using them directly in our own methods. This way we can unit test our
   * own methods.
   *
   * @see user_password
   */
  protected function userLoginFinalize(UserInterface $account) {
    user_login_finalize($account);
  }

  /**
   * Returns an array of fields to initialize the creation of the user.
   *
   * @param string $name
   *   User's name on Provider.
   * @param string $email
   *   User's email address.
   * @param string $langcode
   *   The current UI language.
   *
   * @return array
   *   Fields to initialize for the user creation.
   */
  protected function getUserFields($name, $email, $langcode) {
    // - Password can be very long since the user doesn't see this.
    $fields = [
      'name' => $this->generateUniqueUsername($name),
      'mail' => $email,
      'init' => $email,
      'pass' => $this->userPassword(32),
      'status' => $this->getNewUserStatus(),
      'langcode' => $langcode,
      'preferred_langcode' => $langcode,
      'preferred_admin_langcode' => $langcode,
    ];

    // Dispatches SocialAuthEvents::USER_FIELDS, so that other modules can
    // update this array before an user is saved.
    $event = new SocialAuthUserFieldsEvent($fields, $this->getPluginId());
    $this->eventDispatcher->dispatch(SocialAuthEvents::USER_FIELDS, $event);
    $fields = $event->getUserFields();

    return $fields;
  }

}
