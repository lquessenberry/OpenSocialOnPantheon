<?php

namespace Drupal\Tests\simple_oauth\Kernel;

use Drupal\Core\Session\AccountInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\simple_oauth\Entity\Oauth2Scope;
use Drupal\simple_oauth\Oauth2ScopeInterface;

/**
 * Tests for OAuth2 scope entity.
 *
 * @group simple_oauth
 */
class Oauth2ScopeEntityTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
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

    $this->installSchema('system', ['sequences']);
    $this->installEntitySchema('oauth2_scope');
  }

  /**
   * Tests create operations for OAuth2 scope entity with permission.
   */
  public function testCreateScopePermission(): void {
    $values = [
      'name' => 'test:test',
      'description' => $this->getRandomGenerator()->sentences(5),
      'grant_types' => [
        'authorization_code' => [
          'status' => TRUE,
          'description' => $this->getRandomGenerator()->sentences(5),
        ],
      ],
      'umbrella' => FALSE,
      'parent' => 'test_parent',
      'granularity' => Oauth2ScopeInterface::GRANULARITY_PERMISSION,
      'permission' => 'view own simple_oauth entities',
    ];
    /** @var \Drupal\simple_oauth\Entity\Oauth2ScopeEntityInterface $scope */
    $scope = Oauth2Scope::create($values);
    $scope->save();

    $this->assertEquals(Oauth2Scope::scopeToMachineName($values['name']), $scope->id());
    $this->assertEquals($values['name'], $scope->getName());
    $this->assertEquals($values['description'], $scope->getDescription());
    $this->assertEquals($values['grant_types'], $scope->getGrantTypes());
    $this->assertEquals($values['grant_types']['authorization_code']['description'], $scope->getGrantTypeDescription('authorization_code'));
    $this->assertEquals($values['umbrella'], $scope->isUmbrella());
    $this->assertEquals($values['parent'], $scope->getParent());
    $this->assertEquals($values['granularity'], $scope->getGranularity());
    $this->assertEquals($values['permission'], $scope->getPermission());
  }

  /**
   * Tests create operations for OAuth2 scope entity with role.
   */
  public function testCreateScopeRole(): void {
    $values = [
      'name' => 'test:test',
      'description' => $this->getRandomGenerator()->sentences(5),
      'grant_types' => [
        'client_credentials' => [
          'status' => TRUE,
          'description' => $this->getRandomGenerator()->sentences(5),
        ],
      ],
      'umbrella' => FALSE,
      'parent' => 'test_parent',
      'granularity' => Oauth2ScopeInterface::GRANULARITY_ROLE,
      'role' => AccountInterface::AUTHENTICATED_ROLE,
    ];
    /** @var \Drupal\simple_oauth\Entity\Oauth2ScopeEntityInterface $scope */
    $scope = Oauth2Scope::create($values);
    $scope->save();

    $this->assertEquals(Oauth2Scope::scopeToMachineName($values['name']), $scope->id());
    $this->assertEquals($values['name'], $scope->getName());
    $this->assertEquals($values['description'], $scope->getDescription());
    $this->assertEquals($values['grant_types'], $scope->getGrantTypes());
    $this->assertEquals($values['grant_types']['client_credentials']['description'], $scope->getGrantTypeDescription('client_credentials'));
    $this->assertEquals($values['umbrella'], $scope->isUmbrella());
    $this->assertEquals($values['parent'], $scope->getParent());
    $this->assertEquals($values['granularity'], $scope->getGranularity());
    $this->assertEquals($values['role'], $scope->getRole());
  }

}
