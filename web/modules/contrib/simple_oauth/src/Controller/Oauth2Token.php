<?php

namespace Drupal\simple_oauth\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\simple_oauth\Server\AuthorizationServerFactoryInterface;
use League\OAuth2\Server\Repositories\ClientRepositoryInterface;
use Psr\Http\Message\ResponseInterface;
use GuzzleHttp\Psr7\Response;
use League\OAuth2\Server\Exception\OAuthServerException;
use Symfony\Bridge\PsrHttpMessage\HttpMessageFactoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class OAuth2 Token Controller.
 */
class Oauth2Token extends ControllerBase {

  /**
   * The authorization server factory.
   *
   * @var \Drupal\simple_oauth\Server\AuthorizationServerFactoryInterface
   */
  protected AuthorizationServerFactoryInterface $authorizationServerFactory;

  /**
   * The message factory.
   *
   * @var \Symfony\Bridge\PsrHttpMessage\HttpMessageFactoryInterface
   */
  protected HttpMessageFactoryInterface $httpMessageFactory;

  /**
   * The client repository.
   *
   * @var \League\OAuth2\Server\Repositories\ClientRepositoryInterface
   */
  protected ClientRepositoryInterface $clientRepository;

  /**
   * Oauth2Token constructor.
   *
   * @param \Drupal\simple_oauth\Server\AuthorizationServerFactoryInterface $authorization_server_factory
   *   The authorization server factory.
   * @param \Symfony\Bridge\PsrHttpMessage\HttpMessageFactoryInterface $http_message_factory
   *   The PSR-7 converter.
   * @param \League\OAuth2\Server\Repositories\ClientRepositoryInterface $client_repository
   *   The client repository service.
   */
  public function __construct(
    AuthorizationServerFactoryInterface $authorization_server_factory,
    HttpMessageFactoryInterface $http_message_factory,
    ClientRepositoryInterface $client_repository
  ) {
    $this->authorizationServerFactory = $authorization_server_factory;
    $this->httpMessageFactory = $http_message_factory;
    $this->clientRepository = $client_repository;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('simple_oauth.server.authorization_server.factory'),
      $container->get('psr7.http_message_factory'),
      $container->get('simple_oauth.repositories.client')
    );
  }

  /**
   * Processes POST requests to /oauth/token.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   *
   * @return \Psr\Http\Message\ResponseInterface
   *   The response.
   *
   * @throws \Exception
   */
  public function token(Request $request): ResponseInterface {
    $server_request = $this->httpMessageFactory->createRequest($request);
    $server_response = new Response();
    $client_id = $request->get('client_id');

    try {
      if (empty($client_id)) {
        throw OAuthServerException::invalidRequest('client_id');
      }
      $client_entity = $this->clientRepository->getClientEntity($client_id);
      if (empty($client_entity)) {
        throw OAuthServerException::invalidClient($server_request);
      }
      $client_drupal_entity = $client_entity->getDrupalEntity();

      // Respond to the incoming request and fill in the response.
      $server = $this->authorizationServerFactory->get($client_drupal_entity);
      $response = $server->respondToAccessTokenRequest($server_request, $server_response);
    }
    catch (OAuthServerException $exception) {
      watchdog_exception('simple_oauth', $exception);
      $response = $exception->generateHttpResponse($server_response);
    }

    return $response;
  }

}
