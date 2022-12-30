<?php

namespace Drupal\Tests\group\Kernel;

use Drupal\Core\Cache\CacheBackendInterface;

/**
 * Tests for the GroupContent entity.
 *
 * @group group
 *
 * @coversDefaultClass \Drupal\group\Entity\GroupContent
 */
class GroupContentTest extends GroupKernelTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = ['group_test', 'group_test_plugin'];

  /**
   * Tests that entity url templates are functional.
   *
   * @covers ::urlRouteParameters
   */
  public function testUrlRouteParameters() {
    $group = $this->createGroup();
    $account = $this->createUser();
    $group->addContent($account, 'group_membership');
    $group_content = $group->getContent('group_membership');
    foreach ($group_content as $item) {
      // Canonical.
      $expected = "/group/{$group->id()}/content/{$item->id()}";
      $this->assertEquals($expected, $item->toUrl()->toString());

      // Add form.
      $expected = "/group/{$group->id()}/content/add/group_membership?group_content_type=default-group_membership";
      $this->assertEquals($expected, $item->toUrl('add-form')->toString());

      // Add page.
      $expected = "/group/{$group->id()}/content/add";
      $this->assertEquals($expected, $item->toUrl('add-page')->toString());

      // Collection.
      $expected = "/group/{$group->id()}/content";
      $this->assertEquals($expected, $item->toUrl('collection')->toString());

      // Create form.
      $expected = "/group/{$group->id()}/content/create/group_membership?group_content={$item->id()}";
      $this->assertEquals($expected, $item->toUrl('create-form')->toString());

      // Create page.
      $expected = "/group/{$group->id()}/content/create?group_content={$item->id()}";
      $this->assertEquals($expected, $item->toUrl('create-page')->toString());

      // Delete form.
      $expected = "/group/{$group->id()}/content/{$item->id()}/delete";
      $this->assertEquals($expected, $item->toUrl('delete-form')->toString());

      // Edit form.
      $expected = "/group/{$group->id()}/content/{$item->id()}/edit";
      $this->assertEquals($expected, $item->toUrl('edit-form')->toString());
    }
  }

  /**
   * Tests that after adding an entity to a group, it gets saved again.
   *
   * @covers ::postSave
   *
   * @see group_test_user_update()
   */
  public function testSubjectResaved() {
    $changed = 123456789;
    $account = $this->createUser(['changed' => $changed]);

    $group = $this->createGroup();
    $group->addContent($account, 'group_membership');

    // All users whose changed time was set to 123456789 get their changed time
    // set to 530496000 in group_test_user_update() when the account is updated.
    $account_unchanged = $this->entityTypeManager->getStorage('user')->loadUnchanged($account->id());
    $this->assertEquals(530496000, $account_unchanged->getChangedTime(), 'Account was saved as part of being added to a group.');
  }

  /**
   * Tests that custom list cache tags are properly invalidated.
   *
   * @covers ::getListCacheTagsToInvalidate
   */
  public function testGetCacheTagsToInvalidate() {
    $cache = \Drupal::cache();

    // Create a group type and enable adding users as content.
    $group_type = $this->createGroupType();

    /** @var \Drupal\group\Entity\Storage\GroupContentTypeStorageInterface $storage */
    $storage = $this->entityTypeManager->getStorage('group_content_type');
    $storage->createFromPlugin($group_type, 'user_as_content')->save();

    // Create a group and user to check the cache tags for.
    $test_group = $this->createGroup(['type' => $group_type->id()]);
    $test_group_id = $test_group->id();
    $test_account = $this->createUser();
    $test_account_id = $test_account->id();

    // Create an extra group and account to test with.
    $extra_group = $this->createGroup(['type' => $group_type->id()]);
    $extra_account = $this->createUser();

    $scenarios = [
      // Create a list for specific group, any entity, any plugin.
      'group_content' => ["group_content_list:group:$test_group_id"],
      // Create a list for any group, specific entity, any plugin.
      'content_groups' => ["group_content_list:entity:$test_account_id"],
      // Create a list for any group, any entity, specific plugin.
      'all_memberships' => ["group_content_list:plugin:group_membership"],
      // Create a list for specific group, any entity, specific plugin.
      'group_memberships' => ["group_content_list:plugin:group_membership:group:$test_group_id"],
      // Create a list for any group, specific entity, specific plugin.
      'user_memberships' => ["group_content_list:plugin:group_membership:entity:$test_account_id"],
    ];
    foreach ($scenarios as $cid => $cache_tags) {
      $cache->set($cid, 'foo', CacheBackendInterface::CACHE_PERMANENT, $cache_tags);
    }

    // Add another user to another group and verify cache entries.
    $extra_group->addContent($extra_account, 'user_as_content');
    $this->assertNotFalse($cache->get('group_content'), 'List for specific group, any entity, any plugin found.');
    $this->assertNotFalse($cache->get('content_groups'), 'List for any group, specific entity, any plugin found.');
    $this->assertNotFalse($cache->get('all_memberships'), 'List for any group, any entity, specific plugin found.');
    $this->assertNotFalse($cache->get('group_memberships'), 'List for specific group, any entity, specific plugin found.');
    $this->assertNotFalse($cache->get('user_memberships'), 'List for any group, specific entity, specific plugin found.');

    // Add another user as content to the group and verify cache entries.
    $test_group->addContent($extra_account, 'user_as_content');
    $this->assertFalse($cache->get('group_content'), 'List for specific group, any entity, any plugin cleared.');
    $this->assertNotFalse($cache->get('content_groups'), 'List for any group, specific entity, any plugin found.');
    $this->assertNotFalse($cache->get('all_memberships'), 'List for any group, any entity, specific plugin found.');
    $this->assertNotFalse($cache->get('group_memberships'), 'List for specific group, any entity, specific plugin found.');
    $this->assertNotFalse($cache->get('user_memberships'), 'List for any group, specific entity, specific plugin found.');

    // Add the user as content to another group and verify cache entries.
    $extra_group->addContent($test_account, 'user_as_content');
    $this->assertFalse($cache->get('content_groups'), 'List for any group, specific entity, any plugin cleared.');
    $this->assertNotFalse($cache->get('all_memberships'), 'List for any group, any entity, specific plugin found.');
    $this->assertNotFalse($cache->get('group_memberships'), 'List for specific group, any entity, specific plugin found.');
    $this->assertNotFalse($cache->get('user_memberships'), 'List for any group, specific entity, specific plugin found.');

    // Add any user as a member to any group and verify cache entries.
    $extra_group->addContent($extra_account, 'group_membership');
    $this->assertFalse($cache->get('all_memberships'), 'List for any group, any entity, specific plugin cleared.');
    $this->assertNotFalse($cache->get('group_memberships'), 'List for specific group, any entity, specific plugin found.');
    $this->assertNotFalse($cache->get('user_memberships'), 'List for any group, specific entity, specific plugin found.');

    // Add any user as a member to the group and verify cache entries.
    $test_group->addContent($extra_account, 'group_membership');
    $this->assertFalse($cache->get('group_memberships'), 'List for specific group, any entity, specific plugin cleared.');
    $this->assertNotFalse($cache->get('user_memberships'), 'List for any group, specific entity, specific plugin found.');

    // Add the user as a member to any group and verify cache entries.
    $extra_group->addContent($test_account, 'group_membership');
    $this->assertFalse($cache->get('user_memberships'), 'List for any group, specific entity, specific plugin cleared.');

    // Set the cache again and verify if we add the user to the group.
    foreach ($scenarios as $cid => $cache_tags) {
      $cache->set($cid, 'foo', CacheBackendInterface::CACHE_PERMANENT, $cache_tags);
    }
    $test_group->addContent($test_account, 'group_membership');
    $this->assertFalse($cache->get('group_content'), 'List for specific group, any entity, any plugin cleared.');
    $this->assertFalse($cache->get('content_groups'), 'List for any group, specific entity, any plugin cleared.');
    $this->assertFalse($cache->get('all_memberships'), 'List for any group, any entity, specific plugin cleared.');
    $this->assertFalse($cache->get('group_memberships'), 'List for specific group, any entity, specific plugin cleared.');
    $this->assertFalse($cache->get('user_memberships'), 'List for any group, specific entity, specific plugin cleared.');
  }

}
