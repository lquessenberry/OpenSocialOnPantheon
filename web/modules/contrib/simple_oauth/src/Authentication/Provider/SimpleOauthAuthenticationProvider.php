<?php

namespace Drupal\simple_oauth\Authentication\Provider;

use Drupal\Core\Authentication\AuthenticationProviderInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\simple_oauth\Authentication\TokenAuthUser;
use Drupal\simple_oauth\PageCache\SimpleOauthRequestPolicyInterface;
use Drupal\simple_oauth\Server\ResourceServerFactoryInterface;
use League\OAuth2\Server\Exception\OAuthServerException;
use Symfony\Bridge\PsrHttpMessage\HttpFoundationFactoryInterface;
use Symfony\Bridge\PsrHttpMessage\HttpMessageFactoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * OAuth2 authentication provider.
 *
 * @internal
 */
class SimpleOauthAuthenticationProvider implements AuthenticationProviderInterface {

  use StringTranslationTrait;

  /**
   * The resource server factory.
   *
   * @var \Drupal\simple_oauth\Server\ResourceServerFactoryInterface
   */
  protected ResourceServerFactoryInterface $resourceServerFactory;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The request policy.
   *
   * @var \Drupal\simple_oauth\PageCache\SimpleOauthRequestPolicyInterface
   */
  protected SimpleOauthRequestPolicyInterface $oauthPageCacheRequestPolicy;

  /**
   * The HTTP message factory.
   *
   * @var \Symfony\Bridge\PsrHttpMessage\HttpMessageFactoryInterface
   */
  protected HttpMessageFactoryInterface $httpMessageFactory;

  /**
   * The HTTP foundation factory.
   *
   * @var \Symfony\Bridge\PsrHttpMessage\HttpFoundationFactoryInterface
   */
  protected HttpFoundationFactoryInterface $httpFoundationFactory;

  /**
   * Constructs an HTTP basic authentication provider object.
   *
   * @param \Drupal\simple_oauth\Server\ResourceServerFactoryInterface $resource_server_factory
   *   The resource server factory.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   * @param \Drupal\simple_oauth\PageCache\SimpleOauthRequestPolicyInterface $page_cache_request_policy
   *   The page cache request policy.
   * @param \Symfony\Bridge\PsrHttpMessage\HttpMessageFactoryInterface $http_message_factory
   *   The HTTP message factory.
   * @param \Symfony\Bridge\PsrHttpMessage\HttpFoundationFactoryInterface $http_foundation_factory
   *   The HTTP foundation factory.
   */
  public function __construct(
    ResourceServerFactoryInterface $resource_server_factory,
    EntityTypeManagerInterface $entity_type_manager,
    SimpleOauthRequestPolicyInterface $page_cache_request_policy,
    HttpMessageFactoryInterface $http_message_factory,
    HttpFoundationFactoryInterface $http_foundation_factory
  ) {
    $this->resourceServerFactory = $resource_server_factory;
    $this->entityTypeManager = $entity_type_manager;
    $this->oauthPageCacheRequestPolicy = $page_cache_request_policy;
    $this->httpMessageFactory = $http_message_factory;
    $this->httpFoundationFactory = $http_foundation_factory;
  }

  /**
   * {@inheritdoc}
   */
  public function applies(Request $request) {
    // The request policy service won't be used in case of non GET or HEAD
    // methods, so we have to explicitly call it.
    /* @see \Drupal\Core\PageCache\RequestPolicy\CommandLineOrUnsafeMethod::check() */
    return $this->oauthPageCacheRequestPolicy->isOauth2Request($request);
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \League\OAuth2\Server\Exception\OAuthServerException
   */
  public function authenticate(Request $request) {
    // Update the request with the OAuth information.
    try {
      // Create a PSR-7 message from the request that is compatible with the
      // OAuth library.
      $psr7_request = $this->httpMessageFactory->createRequest($request);
      $resource_server = $this->resourceServerFactory->get();
      $output_psr7_request = $resource_server->validateAuthenticatedRequest($psr7_request);

      // Convert back to the Drupal/Symfony HttpFoundation objects.
      $auth_request = $this->httpFoundationFactory->createRequest($output_psr7_request);
    }
    catch (OAuthServerException $exception) {
      // Procedural code here is hard to avoid.
      watchdog_exception('simple_oauth', $exception);

      throw new HttpException(
        $exception->getHttpStatusCode(),
        $exception->getHint(),
        $exception
      );
    }

    $tokens = $this->entityTypeManager->getStorage('oauth2_token')->loadByProperties([
      'value' => $auth_request->get('oauth_access_token_id'),
    ]);
    $token = reset($tokens);

    $account = new TokenAuthUser($token);

    // Revoke the access token for the blocked user.
    if ($account->isBlocked() && $account->isAuthenticated()) {
      $token->revoke();
      $token->save();
      $exception = OAuthServerException::accessDenied(
        $this->t(
          '%name is blocked or has not been activated yet.',
          ['%name' => $account->getAccountName()]
        )
      );
      watchdog_exception('simple_oauth', $exception);
      throw new HttpException(
        $exception->getHttpStatusCode(),
        $exception->getHint(),
        $exception
      );
    }

    // Inherit uploaded files for the current request.
    /* @link https://www.drupal.org/project/drupal/issues/2934486 */
    $request->files->add($auth_request->files->all());
    // Set consumer ID header on successful authentication, so negotiators
    // will trigger correctly.
    $request->headers->set('X-Consumer-ID', $account->getConsumer()->getClientId());

    return $account;
  }

}
