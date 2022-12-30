<?php

namespace Drupal\Tests\group\Unit;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\PrivateKey;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Site\Settings;
use Drupal\group\Access\CalculatedGroupPermissionsInterface;
use Drupal\group\Access\CalculatedGroupPermissionsItem;
use Drupal\group\Access\CalculatedGroupPermissionsItemInterface;
use Drupal\group\Access\ChainGroupPermissionCalculatorInterface;
use Drupal\group\Access\GroupPermissionsHashGenerator;
use Drupal\group\Access\RefinableCalculatedGroupPermissions;
use Drupal\Tests\UnitTestCase;

/**
 * Tests the group permission hash generator service.
 *
 * @coversDefaultClass \Drupal\group\Access\GroupPermissionsHashGenerator
 * @group group
 */
class GroupPermissionHashGeneratorTest extends UnitTestCase {

  /**
   * The group permissions hash generator service.
   *
   * @var \Drupal\group\Access\GroupPermissionsHashGeneratorInterface
   */
  protected $hashGenerator;

  /**
   * The group permission calculator.
   *
   * @var \Drupal\group\Access\ChainGroupPermissionCalculatorInterface|\Prophecy\Prophecy\ProphecyInterface
   */
  protected $permissionCalculator;

  /**
   * The static cache.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface|\Prophecy\Prophecy\ProphecyInterface
   */
  protected $static;

  /**
   * A dummy account to test with.
   *
   * @var \Drupal\Core\Session\AccountInterface|\Prophecy\Prophecy\ProphecyInterface
   */
  protected $account;

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();
    new Settings(['hash_salt' => 'SALT']);
    $private_key = $this->prophesize(PrivateKey::class);
    $private_key->get()->willReturn('');
    $this->static = $this->prophesize(CacheBackendInterface::class);
    $this->permissionCalculator = $this->prophesize(ChainGroupPermissionCalculatorInterface::class);
    $this->hashGenerator = new GroupPermissionsHashGenerator($private_key->reveal(), $this->static->reveal(), $this->permissionCalculator->reveal());

    $account = $this->prophesize(AccountInterface::class);
    $account->id()->willReturn(24101986);
    $this->account = $account->reveal();
  }

  /**
   * Tests the generation of the account's hash.
   *
   * @covers ::generateHash
   */
  public function testGenerateHash() {
    $scope_gt = CalculatedGroupPermissionsItemInterface::SCOPE_GROUP_TYPE;
    $scope_g = CalculatedGroupPermissionsItemInterface::SCOPE_GROUP;
    $cid = 'group_permissions_hash_24101986';

    $calculated_permissions = new RefinableCalculatedGroupPermissions();
    $this->permissionCalculator->calculatePermissions($this->account)->willReturn($calculated_permissions);

    $sorted_permissions = [
      'alice' => ['bob'],
      'foo' => ['bar', 'baz'],
      16 => ['sweet'],
    ];
    $expected_hash = hash('sha256', 'SALT' . serialize($sorted_permissions));
    $this->static->get($cid)->willReturn(FALSE);
    $this->static->set($cid, $expected_hash, Cache::PERMANENT, [])->shouldBeCalledTimes(1);

    $calculated_permissions
      ->addItem(new CalculatedGroupPermissionsItem($scope_gt, 'foo', ['baz', 'bar']))
      ->addItem(new CalculatedGroupPermissionsItem($scope_gt, 'alice', ['bob']))
      ->addItem(new CalculatedGroupPermissionsItem($scope_g, 16, ['sweet']));
    $this->assertEquals($expected_hash, $this->hashGenerator->generateHash($this->account), 'The hash was generated based on the sorted calculated permissions.');

    $sorted_permissions[100] = 'is-admin';
    $expected_hash = hash('sha256', 'SALT' . serialize($sorted_permissions));
    $this->static->set($cid, $expected_hash, Cache::PERMANENT, [])->shouldBeCalledTimes(1);
    $calculated_permissions
      ->addItem(new CalculatedGroupPermissionsItem($scope_g, 100, ['irrelevant'], TRUE));
    $this->assertEquals($expected_hash, $this->hashGenerator->generateHash($this->account), 'The hash uses a simple flag instead of permissions for admin entries.');

    $cache = (object) ['data' => 'foobar'];
    $this->static->get($cid)->willReturn($cache);
    $this->static->set($cid, 'foobar', Cache::PERMANENT, [])->shouldNotBeCalled();
    $this->assertEquals('foobar', $this->hashGenerator->generateHash($this->account), 'The hash was retrieved from the static cache.');
  }

  /**
   * Tests getting the cacheable metadata from the calculated permissions.
   *
   * @covers ::getCacheableMetadata
   */
  public function testGetCacheableMetadata() {
    $calculated_permissions = $this->prophesize(CalculatedGroupPermissionsInterface::class);
    $calculated_permissions->getCacheContexts()->willReturn([]);
    $calculated_permissions->getCacheTags()->willReturn(["config:group.role.foo-bar"]);
    $calculated_permissions->getCacheMaxAge()->willReturn(-1);
    $calculated_permissions = $calculated_permissions->reveal();
    $this->permissionCalculator->calculatePermissions($this->account)->willReturn($calculated_permissions);
    $this->assertEquals(CacheableMetadata::createFromObject($calculated_permissions), $this->hashGenerator->getCacheableMetadata($this->account));
  }

}
