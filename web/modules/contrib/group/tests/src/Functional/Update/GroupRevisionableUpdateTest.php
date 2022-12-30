<?php

namespace Drupal\group\Tests\Functional\Update;

use Drupal\FunctionalTests\Update\UpdatePathTestBase;

/**
 * Tests that groups are properly made revisionable.
 *
 * @group group
 */
class GroupRevisionableUpdateTest extends UpdatePathTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setDatabaseDumpFiles() {
    $this->databaseDumpFiles = [
      __DIR__ . '/../../../fixtures/update/group-8.x-1.3-pre-revision.php.gz',
    ];
  }

  /**
   * Tests that the data in the revision table is properly set.
   */
  public function testRevisionData() {
    /** @var \Drupal\group\Entity\GroupInterface $group */
    $group_storage = \Drupal::entityTypeManager()->getStorage('group');
    $group = $group_storage->load(1);
    $group_user_id = $group->getOwnerId();
    $group_created = $group->getCreatedTime();

    $this->runUpdates();

    // Reload the storage because the update changed it.
    $group_storage = \Drupal::entityTypeManager()->getStorage('group');
    $group = $group_storage->loadRevision(1);
    $this->assertEquals($group_user_id, $group->getRevisionUserId());
    $this->assertEquals($group_created, $group->getRevisionCreationTime());
    $this->assertEquals('', $group->getRevisionLogMessage());
  }

}
