<?php

namespace Drupal\r4032login\EventSubscriber;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\EventSubscriber\HttpExceptionSubscriberBase;
use Drupal\Core\Path\PathMatcherInterface;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Routing\RedirectDestinationInterface;
use Drupal\Core\Url;
use Drupal\r4032login\Event\RedirectEvent;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\Component\Utility\Xss;

/**
 * Redirect 403 to User Login event subscriber.
 */
class R4032LoginSubscriber extends HttpExceptionSubscriberBase {

  /**
   * The configuration factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * The redirect destination service.
   *
   * @var \Drupal\Core\Routing\RedirectDestinationInterface
   */
  protected $redirectDestination;

  /**
   * The path matcher.
   *
   * @var \Drupal\Core\Path\PathMatcherInterface
   */
  protected $pathMatcher;

  /**
   * An event dispatcher instance to use for map events.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected $eventDispatcher;

  /**
   * Constructs a new R4032LoginSubscriber.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The configuration factory.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user.
   * @param \Drupal\Core\Routing\RedirectDestinationInterface $redirect_destination
   *   The redirect destination service.
   * @param \Drupal\Core\Path\PathMatcherInterface $path_matcher
   *   The path matcher.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher
   *   The event dispatcher.
   */
  public function __construct(ConfigFactoryInterface $config_factory, AccountInterface $current_user, RedirectDestinationInterface $redirect_destination, PathMatcherInterface $path_matcher, EventDispatcherInterface $event_dispatcher) {
    $this->configFactory = $config_factory;
    $this->currentUser = $current_user;
    $this->redirectDestination = $redirect_destination;
    $this->pathMatcher = $path_matcher;
    $this->eventDispatcher = $event_dispatcher;
  }

  /**
   * {@inheritdoc}
   */
  protected function getHandledFormats() {
    return ['html'];
  }

  /**
   * Redirects on 403 Access Denied kernel exceptions.
   *
   * @param \Symfony\Component\HttpKernel\Event\GetResponseEvent $event
   *   The Event to process.
   */
  public function on403(GetResponseEvent $event) {
    $config = $this->configFactory->get('r4032login.settings');

    // Check if the path should be ignored.
    $noRedirectPages = trim($config->get('match_noredirect_pages'));
    if ($noRedirectPages !== '') {
      $pathToMatch = $this->redirectDestination->get();

      try {
        // Clean up path from possible language prefix, GET arguments, etc.
        $pathToMatch = '/' . Url::fromUserInput($pathToMatch)->getInternalPath();
      }
      catch (\Exception $e) {
      }

      if ($this->pathMatcher->matchPath($pathToMatch, $noRedirectPages)) {
        return;
      }
    }

    // Retrieve the redirect path depending if the user is logged or not.
    if ($this->currentUser->isAnonymous()) {
      $redirectPath = $config->get('user_login_path');
    }
    else {
      $redirectPath = $config->get('redirect_authenticated_users_to');
    }

    if (!empty($redirectPath)) {
      // Determine if the redirect path is external.
      $externalRedirect = UrlHelper::isExternal($redirectPath);

      // Determine the HTTP redirect code.
      $code = $config->get('default_redirect_code');

      // Determine the url options.
      $options = [
        'absolute' => TRUE,
      ];

      // Determine the destination parameter
      // and add it as options for the url build.
      if ($config->get('redirect_to_destination')) {
        $destination = $this->redirectDestination->get();

        if ($externalRedirect) {
          $destination = Url::fromUserInput($destination, [
            'absolute' => TRUE,
          ])->toString();
        }

        if (empty($config->get('destination_parameter_override'))) {
          $options['query']['destination'] = $destination;
        }
        else {
          $options['query'][$config->get('destination_parameter_override')] = $destination;
        }
      }

      // Allow to alter the url or options before to redirect.
      $redirectEvent = new RedirectEvent($redirectPath, $options);
      $this->eventDispatcher->dispatch(RedirectEvent::EVENT_NAME, $redirectEvent);
      $redirectPath = $redirectEvent->getUrl();
      $options = $redirectEvent->getOptions();

      // Perform the redirection.
      if ($externalRedirect) {
        $url = Url::fromUri($redirectPath, $options)->toString();
        $response = new TrustedRedirectResponse($url);
      }
      else {
        // Show custom access denied message if set.
        if ($this->currentUser->isAnonymous() && $config->get('display_denied_message')) {
          $message = $config->get('access_denied_message');
          $messageType = $config->get('access_denied_message_type');
          drupal_set_message(Xss::filterAdmin($message), $messageType);
        }

        if ($redirectPath === '<front>') {
          $url = \Drupal::urlGenerator()->generate('<front>');
        }
        else {
          $url = Url::fromUserInput($redirectPath, $options)->toString();
        }

        $response = new RedirectResponse($url, $code);
      }

      $event->setResponse($response);
    }
  }

}
