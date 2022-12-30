<?php

namespace Drupal\Tests\group\Unit;

use Drupal\group\Access\CalculatedGroupPermissionsItem;
use Drupal\group\Access\CalculatedGroupPermissionsItemInterface;
use Drupal\Tests\UnitTestCase;

/**
 * Tests the CalculatedGroupPermissionsItem value object.
 *
 * @coversDefaultClass \Drupal\group\Access\CalculatedGroupPermissionsItem
 * @group group
 */
class CalculatedGroupPermissionsItemTest extends UnitTestCase {

  /**
   * Tests that the object values were set in the constructor.
   *
   * @covers ::__construct
   * @covers ::getIdentifier
   * @covers ::getScope
   * @covers ::getPermissions
   * @covers ::isAdmin
   */
  public function testConstructor() {
    $scope = CalculatedGroupPermissionsItemInterface::SCOPE_GROUP_TYPE;
    $item = new CalculatedGroupPermissionsItem($scope, 'foo', ['bar', 'baz', 'bar'], FALSE);
    $this->assertEquals($scope, $item->getScope(), 'Scope name was set correctly.');
    $this->assertEquals('foo', $item->getIdentifier(), 'Scope identifier was set correctly.');
    $this->assertEquals(['bar', 'baz'], $item->getPermissions(), 'Permissions were made unique and set correctly.');
    $this->assertFalse($item->isAdmin(), 'Admin flag was set correctly');
  }

  /**
   * Tests the permission check when the admin flag is not set.
   *
   * @covers ::hasPermission
   * @depends testConstructor
   */
  public function testHasPermission() {
    $scope = CalculatedGroupPermissionsItemInterface::SCOPE_GROUP_TYPE;
    $item = new CalculatedGroupPermissionsItem($scope, 'foo', ['bar'], FALSE);
    $this->assertFalse($item->hasPermission('baz'), 'Missing permission was not found.');
    $this->assertTrue($item->hasPermission('bar'), 'Existing permission was found.');
  }

  /**
   * Tests the permission check when the admin flag is set.
   *
   * @covers ::hasPermission
   * @depends testConstructor
   */
  public function testHasPermissionWithAdminFlag() {
    $scope = CalculatedGroupPermissionsItemInterface::SCOPE_GROUP_TYPE;
    $item = new CalculatedGroupPermissionsItem($scope, 'foo', ['bar'], TRUE);
    $this->assertTrue($item->hasPermission('baz'), 'Missing permission was found.');
    $this->assertTrue($item->hasPermission('bar'), 'Existing permission was found.');
  }

}
