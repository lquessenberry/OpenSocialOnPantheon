<?php

namespace Drupal\Tests\profile\Kernel;

use Drupal\Core\Session\AccountInterface;
use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use Drupal\profile\ProfileTestTrait;
use Drupal\user\Entity\Role;

/**
 * Tests profile role access handling.
 *
 * @group profile
 */
class ProfileRoleAccessTest extends EntityKernelTestBase {

  use ProfileTestTrait;

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = [
    'entity',
    'profile',
    'views',
  ];

  /**
   * Randomly generated profile type entity.
   *
   * No roles.
   *
   * @var \Drupal\profile\Entity\ProfileType
   */
  protected $type1;

  /**
   * Randomly generated profile type entity.
   *
   * Requires some, but not all roles.
   *
   * @var \Drupal\profile\Entity\ProfileType
   */
  protected $type2;

  /**
   * Randomly generated profile type entity.
   *
   * Requires all profile roles.
   *
   * @var \Drupal\profile\Entity\ProfileType
   */
  protected $type3;

  /**
   * Randomly generated user role entity.
   *
   * @var \Drupal\user\Entity\Role
   */
  protected $role1;

  /**
   * Randomly generated user role entity.
   *
   * @var \Drupal\user\Entity\Role
   */
  protected $role2;

  /**
   * The profile access handler.
   *
   * @var \Drupal\profile\ProfileAccessControlHandler
   */
  protected $accessHandler;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->role1 = Role::create([
      'id' => strtolower($this->randomMachineName(8)),
      'label' => $this->randomMachineName(8),
    ]);
    $this->role1->save();
    $this->role2 = Role::create([
      'id' => strtolower($this->randomMachineName(8)),
      'label' => $this->randomMachineName(8),
    ]);
    $this->role2->save();
    $this->type1 = $this->createProfileType(NULL, NULL, FALSE, []);
    $this->type2 = $this->createProfileType(NULL, NULL, FALSE, [$this->role2->id()]);
    $this->type3 = $this->createProfileType(NULL, NULL, FALSE, [
      $this->role1->id(),
      $this->role2->id(),
    ]);

    $this->accessHandler = $this->container->get('entity_type.manager')
      ->getAccessControlHandler('profile');

    // Do not allow uid == 1 to skew tests.
    $this->createUser();
  }

  /**
   * Tests profile form access for a type that has no role requirement.
   */
  public function testProfileWithNoRoles() {
    // Create user with add profile permissions.
    $web_user1 = $this->createUser([], ["create {$this->type1->id()} profile"]);
    $this->assertTrue($this->accessHandler->createAccess($this->type1->id(), $web_user1));
  }

  /**
   * Tests profile form access for a type that has the locked role requirement.
   */
  public function testLockedRoles() {
    $locked_role_type = $this->createProfileType(NULL, NULL, FALSE, [AccountInterface::AUTHENTICATED_ROLE]);
    // Create user with add profile permissions.
    $web_user1 = $this->createUser([], ["create {$locked_role_type->id()} profile"]);
    $this->assertTrue($this->accessHandler->createAccess($locked_role_type->id(), $web_user1));
  }

  /**
   * Tests profile form access for a type that requires a role.
   */
  public function testProfileWithSingleRole() {
    // Create user with add own profile permissions.
    $web_user1 = $this->createUser([], ["create {$this->type2->id()} profile"]);

    // Test user without role can access add profile form.
    // Expected: User cannot access form.
    $this->assertFalse($this->accessHandler->createAccess($this->type2->id(), $web_user1));
    $this->accessHandler->resetCache();

    // Test user with wrong role can access add profile form.
    // Expected: User cannot access form.
    $web_user1->addRole($this->role1->id());
    $web_user1->save();

    $this->assertFalse($this->accessHandler->createAccess($this->type2->id(), $web_user1));
    $this->accessHandler->resetCache();

    // Test user with correct role can access add profile form.
    // Expected: User can access form.
    $web_user1->removeRole($this->role1->id());
    $web_user1->addRole($this->role2->id());
    $web_user1->save();
    $this->reloadEntity($web_user1);

    $this->assertTrue($this->accessHandler->createAccess($this->type2->id(), $web_user1));
  }

  /**
   * Tests profile form access for a type that requires multiple roles.
   */
  public function testProfileWithAllRoles() {
    // Create user with add own profile permissions.
    $web_user1 = $this->createUser([], ["create {$this->type3->id()} profile"]);

    // Test user without role can access add profile form.
    // Expected: User cannot access form.
    $this->assertFalse($this->accessHandler->createAccess($this->type3->id(), $web_user1));
    $this->accessHandler->resetCache();

    // Test user with role 1 can access add profile form.
    // Expected: User can access form.
    $web_user1->addRole($this->role1->id());
    $web_user1->save();

    $this->assertTrue($this->accessHandler->createAccess($this->type3->id(), $web_user1));
    $this->accessHandler->resetCache();

    // Test user with both roles can access add profile form.
    // Expected: User can access form.
    $web_user1->addRole($this->role2->id());
    $web_user1->save();

    $this->assertTrue($this->accessHandler->createAccess($this->type3->id(), $web_user1));
    $this->accessHandler->resetCache();

    // Test user with role 2 can access add profile form.
    // Expected: User can access form.
    $web_user1->removeRole($this->role1->id());
    $web_user1->save();

    $this->assertTrue($this->accessHandler->createAccess($this->type3->id(), $web_user1));
    $this->accessHandler->resetCache();

    // Test user without role can access add profile form.
    // Expected: User cannot access form.
    $web_user1->removeRole($this->role2->id());
    $web_user1->save();

    $this->assertFalse($this->accessHandler->createAccess($this->type3->id(), $web_user1));
  }

}
