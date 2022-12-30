<?php

namespace Drupal\Tests\ginvite\Functional;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\group\Entity\GroupContent;
use Drupal\Tests\group\Functional\GroupBrowserTestBase;

/**
 * Tests the behavior of the group invite expire functionality.
 *
 * @group group
 */
class GroupInviteExpireTest extends GroupBrowserTestBase {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'group',
    'group_test_config',
    'ginvite',
  ];

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The group we will use to test methods on.
   *
   * @var \Drupal\group\Entity\Group
   */
  protected $group;

  /**
   * The normal user we will use.
   *
   * @var \Drupal\user\Entity\User
   */
  protected $account;

  /**
   * The cron service.
   *
   * @var \Drupal\Core\Cron
   */
  protected $cron;

  /**
   * Gets the global (site) permissions for the group creator.
   *
   * @return string[]
   *   The permissions.
   */
  protected function getGlobalPermissions() {
    return [
      'view the administration theme',
      'access administration pages',
      'access group overview',
      'create default group',
      'create other group',
      'administer group',
      'bypass group access',
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->group = $this->createGroup(['uid' => $this->groupCreator->id()]);
    $this->entityTypeManager = $this->container->get('entity_type.manager');

    // Add permissions to members of the group.
    $role = $this->group->getGroupType()->getMemberRole();
    $role->grantPermissions(['edit group']);
    $role->save();

    $this->cron = \Drupal::service('cron');
  }

  /**
   * Create invites and let them expire.
   */
  public function testExpireInvites() {
    $this->drupalLogin($this->groupCreator);
    $expire_days = 14;

    // Install and configure the Group Invitation plugin.
    $this->drupalGet('/admin/group/content/install/default/group_invitation');
    $this->assertSession()->fieldExists('invitation_expire');
    $this->submitForm(['invitation_expire' => $expire_days], 'Install plugin');
    $this->assertSession()->statusCodeEquals(200);

    // @todo get rid of this cache clear. But without it the group invitation
    // plugin config doesn't seem to be available.
    drupal_flush_all_caches();

    // Create an invite.
    $this->drupalGet('/group/1/content/add/group_invitation');
    $this->submitForm(['invitee_mail[0][value]' => 'test@test.local'], 'Save');
    $this->assertSession()->statusCodeEquals(200);

    // Create another invite.
    $this->drupalGet('/group/1/content/add/group_invitation');
    $this->submitForm(['invitee_mail[0][value]' => 'test2@test.local'], 'Save');
    $this->assertSession()->statusCodeEquals(200);

    // Expire the first invite.
    /** @var \Drupal\group\Entity\GroupContentInterface $invite */
    $invite = GroupContent::load(2);
    $invite->set('created', ($expire_days * 86400) - 1);
    $invite->save();

    // Run the cron.
    $this->container
      ->get('state')
      ->set('ginvite.last_expire_removal', 0);
    $this->cron->run();

    // We forced the first invite to expire, so that one should be deleted.
    $invite = GroupContent::load(2);
    $this->assertNull($invite);

    // Nothing changed here, should still be available.
    $invite = GroupContent::load(3);
    $this->assertIsObject($invite);
  }

}
