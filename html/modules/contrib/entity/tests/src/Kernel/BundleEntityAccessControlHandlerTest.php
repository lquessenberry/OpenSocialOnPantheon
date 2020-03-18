<?php

namespace Drupal\Tests\entity\Kernel;

use Drupal\entity_module_test\Entity\EnhancedEntityBundle;
use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;

/**
 * Tests the bundle entity access control handler.
 *
 * @group entity
 */
class BundleEntityAccessControlHandlerTest extends EntityKernelTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'entity',
    'entity_module_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->installEntitySchema('entity_test_enhanced');
  }

  /**
   * Tests the "view label" access checking.
   */
  public function testAccess() {
    $bundle = EnhancedEntityBundle::create([
      'id' => 'default',
      'label' => 'Default',
    ]);
    $bundle->save();

    // The default user has no permissions related to entity_test_enhanced.
    $this->assertFalse($bundle->access('view label'));

    $permissions = [
      'administer entity_test_enhanced',
      'view entity_test_enhanced',
      'view default entity_test_enhanced',
    ];
    foreach ($permissions as $permission) {
      $account = $this->createUser([], [$permission]);
      $this->assertTrue($bundle->access('view label', $account));
    }
  }

}
