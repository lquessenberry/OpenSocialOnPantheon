<?php

namespace Drupal\Tests\simple_oauth\Kernel;

use Drupal\consumers\Entity\Consumer;
use Drupal\KernelTests\KernelTestBase;
use Drupal\simple_oauth\Entity\Oauth2Scope;
use Drupal\simple_oauth\Oauth2ScopeInterface;

/**
 * Tests for consumer entity.
 *
 * @group simple_oauth
 */
class ConsumerEntityTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'consumers',
    'file',
    'image',
    'options',
    'serialization',
    'system',
    'simple_oauth',
    'user',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('file');
    $this->installEntitySchema('consumer');
    $this->installEntitySchema('oauth2_token');
    $this->installEntitySchema('user');
    $this->installConfig(['user']);
    $this->installConfig(['simple_oauth']);
  }

  /**
   * Tests create operation for consumer entity.
   */
  public function testCreate(): void {
    $scope = Oauth2Scope::create([
      'name' => 'test:test',
      'description' => $this->getRandomGenerator()->sentences(5),
      'grant_types' => [
        'authorization_code' => [
          'status' => TRUE,
        ],
        'client_credentials' => [
          'status' => TRUE,
        ],
      ],
      'umbrella' => FALSE,
      'granularity' => Oauth2ScopeInterface::GRANULARITY_PERMISSION,
      'permission' => 'view own simple_oauth entities',
    ]);
    $scope->save();
    $values = [
      'client_id' => 'test_client',
      'label' => 'test',
      'grant_types' => ['authorization_code', 'client_credentials'],
      'scopes' => [$scope->id()],
      'confidential' => TRUE,
      'pkce' => TRUE,
      'redirect' => [
        'mobile://test.com',
        'http://localhost',
      ],
      'access_token_expiration' => 600,
      'refresh_token_expiration' => 2419200,
      'automatic_authorization' => TRUE,
      'remember_approval' => FALSE,
    ];
    $consumer = Consumer::create($values);
    $consumer->save();

    $this->assertEquals($values['client_id'], $consumer->getClientId());
    $this->assertEquals($values['label'], $consumer->label());
    foreach ($values['grant_types'] as $delta => $grant_type) {
      $this->assertEquals($grant_type, $consumer->get('grant_types')->get($delta)->value);
    }
    foreach ($values['scopes'] as $delta => $scope) {
      $this->assertEquals($scope, $consumer->get('scopes')->get($delta)->scope_id);
      $this->assertInstanceOf(Oauth2ScopeInterface::class, $consumer->get('scopes')->get($delta)->getScope());
    }
    $this->assertEquals($values['confidential'], $consumer->get('confidential')->value);
    $this->assertEquals($values['pkce'], $consumer->get('pkce')->value);
    foreach ($values['redirect'] as $delta => $redirect) {
      $this->assertEquals($redirect, $consumer->get('redirect')->get($delta)->value);
    }
    $this->assertEquals($values['access_token_expiration'], $consumer->get('access_token_expiration')->value);
    $this->assertEquals($values['refresh_token_expiration'], $consumer->get('refresh_token_expiration')->value);
    $this->assertEquals($values['automatic_authorization'], $consumer->get('automatic_authorization')->value);
    $this->assertEquals($values['remember_approval'], $consumer->get('remember_approval')->value);
  }

  /**
   * Test default values for the enriched BaseFields on the consumer entity.
   */
  public function testDefaultValues(): void {
    $consumer = Consumer::create([
      'client_id' => 'test_client',
      'label' => 'test client',
      'grant_types' => ['authorization_code'],
      'redirect' => [
        'http://test',
      ],
    ]);
    $consumer->save();

    $this->assertEquals(300, $consumer->get('access_token_expiration')->value);
    $this->assertEquals(1209600, $consumer->get('refresh_token_expiration')->value);
    $this->assertEquals(FALSE, (bool) $consumer->get('automatic_authorization')->value);
    $this->assertEquals(TRUE, (bool) $consumer->get('remember_approval')->value);
    $this->assertEquals(TRUE, (bool) $consumer->get('confidential')->value);
    $this->assertEquals(FALSE, (bool) $consumer->get('pkce')->value);
  }

}
