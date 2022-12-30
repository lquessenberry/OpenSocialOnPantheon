<?php

namespace Drupal\r4032login\EventSubscriber;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Component\Utility\Xss;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Cache\CacheableRedirectResponse;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\EventSubscriber\HttpExceptionSubscriberBase;
use Drupal\Core\Http\Exception\CacheableNotFoundHttpException;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Path\PathMatcherInterface;
use Drupal\Core\Render\Markup;
use Drupal\Core\Routing\RedirectDestinationInterface;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\r4032login\Event\RedirectEvent;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;

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
   * The messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * The redirect destination service.
   *
   * @var \Drupal\Core\Routing\RedirectDestinationInterface
   */
  protected $redirectDestination;

  /**
   * Constructs a new R4032LoginSubscriber.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The configuration factory.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user.
   * @param \Drupal\Core\Path\PathMatcherInterface $path_matcher
   *   The path matcher.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher
   *   The event dispatcher.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   * @param \Drupal\Core\Routing\RedirectDestinationInterface $redirect_destination
   *   The redirect destination service.
   */
  public function __construct(ConfigFactoryInterface $config_factory, AccountInterface $current_user, PathMatcherInterface $path_matcher, EventDispatcherInterface $event_dispatcher, MessengerInterface $messenger, RedirectDestinationInterface $redirect_destination) {
    $this->configFactory = $config_factory;
    $this->currentUser = $current_user;
    $this->pathMatcher = $path_matcher;
    $this->eventDispatcher = $event_dispatcher;
    $this->messenger = $messenger;
    $this->redirectDestination = $redirect_destination;
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
   * @param \Symfony\Component\HttpKernel\Event\ExceptionEvent $event
   *   The Event to process.
   */
  public function on403(ExceptionEvent $event) {
    $config = $this->configFactory->get('r4032login.settings');

    $request = $event->getRequest();
    $currentPath = $request->getPathInfo();

    // Check if the path should be ignored.
    if (($noRedirectPages = trim($config->get('match_noredirect_pages')))
      && (($this->pathMatcher->matchPath($currentPath, $noRedirectPages) && !$config->get('match_noredirect_negate'))
      || (!$this->pathMatcher->matchPath($currentPath, $noRedirectPages) && $config->get('match_noredirect_negate')))
    ) {
      return;
    }

    // Retrieve the redirect path depending if the user is logged or not.
    if ($this->currentUser->isAnonymous()) {
      $redirectPath = $config->get('user_login_path');
    }
    elseif ($config->get('throw_authenticated_404')) {
      // Inherit cacheable metadata from the original throwable object.
      $originalCacheableMetadata = new CacheableMetadata();
      $originalCacheableMetadata->addCacheableDependency($event->getThrowable());
      $event->setThrowable(new CacheableNotFoundHttpException($originalCacheableMetadata));
    }
    else {
      $redirectPath = $config->get('redirect_authenticated_users_to');
    }

    if (!empty($redirectPath)) {
      // Determine if the redirect path is external.
      $externalRedirect = UrlHelper::isExternal($redirectPath);

      // Determine the url options.
      $options = [
        'absolute' => TRUE,
      ];

      // Determine the destination parameter
      // and add it as options for the url build.
      if ($config->get('redirect_to_destination')) {
        if ($externalRedirect) {
          $destination = Url::fromUserInput($currentPath, [
            'absolute' => TRUE,
          ])->toString();

          if ($queryString = $request->getQueryString()) {
            $destination .= '?' . $queryString;
          }
        }
        else {
          $destination = $this->redirectDestination->get();
        }

        if (empty($config->get('destination_parameter_override'))) {
          $options['query']['destination'] = $destination;
        }
        else {
          $options['query'][$config->get('destination_parameter_override')] = $destination;
        }
      }

      // Remove the destination parameter to allow redirection.
      $request->query->remove('destination');

      // Allow to alter the url or options before to redirect.
      $redirectEvent = new RedirectEvent($redirectPath, $options);
      $this->eventDispatcher->dispatch($redirectEvent, RedirectEvent::EVENT_NAME);
      $redirectPath = $redirectEvent->getUrl();
      $options = $redirectEvent->getOptions();

      $code = $config->get('default_redirect_code');

      $headers = [];
      if ($config->get('add_noindex_header')) {
        $headers['X-Robots-Tag'] = 'noindex';
      }

      // Perform the redirection.
      if ($externalRedirect) {
        $url = Url::fromUri($redirectPath, $options)->toString();
        $response = new TrustedRedirectResponse($url, $code, $headers);
      }
      else {
        // Show custom access denied message if set.
        if ($this->currentUser->isAnonymous() && $config->get('display_denied_message')) {
          $message = $config->get('access_denied_message');
          $messageType = $config->get('access_denied_message_type');
          $this->messenger->addMessage(Markup::create(Xss::filterAdmin($message)), $messageType);
        }
        if ($this->currentUser->isAuthenticated()) {
          // If user is authenticated, remove destination to prevent looping.
          if (!empty($options['query']) && !empty($options['query']['destination'])) {
            unset($options['query']);
          }
          // Show custom access denied message for authenticated users if set.
          if ($config->get('display_auth_denied_message')) {
            $message = $config->get('access_denied_auth_message');
            $messageType = $config->get('access_denied_auth_message_type');
            $this->messenger->addMessage(Markup::create(Xss::filterAdmin($message)), $messageType);
          }
        }

        if ($redirectPath === '<front>') {
          $url = Url::fromRoute('<front>', [], $options)->toString();
        }
        else {
          $url = Url::fromUserInput($redirectPath, $options)->toString();
        }

        $response = new CacheableRedirectResponse($url, $code, $headers);
      }

      // Add caching dependencies so the cache of the redirection will be
      // updated when necessary.
      $cacheableMetadata = new CacheableMetadata();
      // Add original 403 response cache metadata.
      $cacheableMetadata->addCacheableDependency($event->getThrowable());
      // We still need to add the client error tag manually since the core
      // wil not recognize our redirection as an error.
      $cacheableMetadata->addCacheTags(['4xx-response']);
      // Add our config cache metadata.
      $cacheableMetadata->addCacheableDependency($config);
      // Attach cache metadata to the response.
      $response->addCacheableDependency($cacheableMetadata);

      $event->setResponse($response);
    }
  }

}
