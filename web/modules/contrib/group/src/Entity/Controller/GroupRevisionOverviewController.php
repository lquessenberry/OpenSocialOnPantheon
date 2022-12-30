<?php

namespace Drupal\group\Entity\Controller;

use Drupal\Core\Entity\EntityInterface;
use Drupal\entity\Controller\RevisionOverviewController;

/**
 * Returns responses for Group revision UI routes.
 */
class GroupRevisionOverviewController extends RevisionOverviewController {

  /**
   * {@inheritdoc}
   */
  protected function hasRevertRevisionAccess(EntityInterface $group) {
    /** @var \Drupal\group\Entity\GroupInterface $group */
    return $group->hasPermission('revert group revisions', $this->currentUser());
  }

  /**
   * {@inheritdoc}
   */
  protected function hasDeleteRevisionAccess(EntityInterface $group) {
    /** @var \Drupal\group\Entity\GroupInterface $group */
    return $group->hasPermission('delete group revisions', $this->currentUser());
  }

}
