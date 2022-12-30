<?php

namespace Drupal\Tests\simple_oauth\Kernel;

use Drupal\Component\Serialization\Json;
use Symfony\Component\HttpFoundation\Request;

/**
 * The client credentials test.
 *
 * @group simple_oauth
 */
class ClientCredentialsTest extends AuthorizedRequestBase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    // Client credentials need a valid default user set.
    $this->client->set('user_id', $this->user)->save();
  }

  /**
   * Ensure incorrectly-configured clients without a user are unusable.
   */
  public function testMisconfiguredClient(): void {
    $this->client->set('user_id', NULL)->save();
    $request = Request::create($this->url->toString(), 'POST', [
      'grant_type' => 'client_credentials',
      'client_id' => $this->client->getClientId(),
      'client_secret' => $this->clientSecret,
      'scope' => $this->scope,
    ]);
    $response = $this->httpKernel->handle($request);
    $parsed_response = Json::decode((string) $response->getContent());

    $this->assertEquals(500, $response->getStatusCode());
    $this->assertStringContainsString('Invalid default user for client.', $parsed_response['message']);
  }

  /**
   * Test the valid ClientCredentials grant.
   */
  public function testClientCredentialsGrant(): void {
    // 1. Test the valid response.
    $parameters = [
      'grant_type' => 'client_credentials',
      'client_id' => $this->client->getClientId(),
      'client_secret' => $this->clientSecret,
      'scope' => $this->scope,
    ];
    $request = Request::create($this->url->toString(), 'POST', $parameters);
    $response = $this->httpKernel->handle($request);
    $this->assertValidTokenResponse($response);

    // 2. Test default scopes on the client.
    unset($parameters['scope']);
    $request = Request::create($this->url->toString(), 'POST', $parameters);
    $response = $this->httpKernel->handle($request);
    $parsed_response = $this->assertValidTokenResponse($response);
    $this->assertAccessTokenOnResource($parsed_response['access_token']);
  }

  /**
   * Data provider for ::testMissingClientCredentialsGrant.
   */
  public function missingClientCredentialsProvider(): array {
    return [
      'grant_type' => [
        'grant_type',
        'unsupported_grant_type',
        400,
      ],
      'client_id' => [
        'client_id',
        'invalid_request',
        400,
      ],
      'client_secret' => [
        'client_secret',
        'invalid_client',
        401,
      ],
    ];
  }

  /**
   * Test invalid ClientCredentials grant.
   *
   * @dataProvider missingClientCredentialsProvider
   */
  public function testMissingClientCredentialsGrant(string $key, string $error, int $code): void {
    $parameters = [
      'grant_type' => 'client_credentials',
      'client_id' => $this->client->getClientId(),
      'client_secret' => $this->clientSecret,
      'scope' => $this->scope,
    ];
    unset($parameters[$key]);
    $request = Request::create($this->url->toString(), 'POST', $parameters);
    $response = $this->httpKernel->handle($request);
    $parsed_response = Json::decode((string) $response->getContent());
    $this->assertSame($error, $parsed_response['error'], sprintf('Correct error code %s', $error));
    $this->assertSame($code, $response->getStatusCode(), sprintf('Correct status code %d', $code));
  }

  /**
   * Data provider for ::testInvalidClientCredentialsGrant.
   */
  public function invalidClientCredentialsProvider(): array {
    return [
      'grant_type' => [
        'grant_type',
        'unsupported_grant_type',
        400,
      ],
      'client_id' => [
        'client_id',
        'invalid_client',
        401,
      ],
      'client_secret' => [
        'client_secret',
        'invalid_client',
        401,
      ],
    ];
  }

  /**
   * Test invalid ClientCredentials grant.
   *
   * @dataProvider invalidClientCredentialsProvider
   */
  public function testInvalidClientCredentialsGrant(string $key, string $error, int $code): void {
    $parameters = [
      'grant_type' => 'client_credentials',
      'client_id' => $this->client->getClientId(),
      'client_secret' => $this->clientSecret,
      'scope' => $this->scope,
    ];
    $parameters[$key] = $this->randomString();
    $request = Request::create($this->url->toString(), 'POST', $parameters);
    $response = $this->httpKernel->handle($request);
    $parsed_response = Json::decode((string) $response->getContent());
    $this->assertSame($error, $parsed_response['error'], sprintf('Correct error code %s', $error));
    $this->assertSame($code, $response->getStatusCode(), sprintf('Correct status code %d', $code));
  }

}
