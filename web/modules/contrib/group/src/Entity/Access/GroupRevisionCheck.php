<?php

namespace Drupal\group\Entity\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\group\Access\GroupAccessResult;
use Drupal\group\Entity\GroupInterface;
use Symfony\Component\Routing\Route;

/**
 * Checks access to a group revision.
 */
class GroupRevisionCheck implements AccessInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The currently active route match object.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $routeMatch;

  /**
   * Stores calculated access check results.
   *
   * @var \Drupal\Core\Access\AccessResultInterface[]
   */
  protected $accessCache = [];

  /**
   * Constructs a new GroupRevisionCheck.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The currently active route match object.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, RouteMatchInterface $route_match) {
    $this->entityTypeManager = $entity_type_manager;
    $this->routeMatch = $route_match;
  }

  /**
   * Checks routing access for group revision operations.
   *
   * @param \Symfony\Component\Routing\Route $route
   *   The route to check against.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The currently logged in account.
   * @param \Drupal\Core\Routing\RouteMatchInterface|null $route_match
   *   (optional) The route match.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access(Route $route, AccountInterface $account, RouteMatchInterface $route_match = NULL) {
    if (empty($route_match)) {
      $route_match = $this->routeMatch;
    }

    $operation = $route->getRequirement('_group_revision');
    if ($operation === 'list') {
      $group = $route_match->getParameter('group');
      return $this->checkAccess($group, $account);
    }
    else {
      $group_revision = $route_match->getParameter('group_revision');
      return $this->checkAccess($group_revision, $account, $operation);
    }
  }

  /**
   * Checks group revision access.
   *
   * @param \Drupal\group\Entity\GroupInterface $group
   *   The group or group revision to check.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   A user object representing the user for whom the operation is to be
   *   performed.
   * @param string $operation
   *   (optional) The specific operation being checked. Defaults to 'view'.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function checkAccess(GroupInterface $group, AccountInterface $account, $operation = 'view') {
    $map = [
      'view' => 'view group revisions',
      'update' => 'revert group revisions',
      'delete' => 'delete group revisions',
    ];

    if (!isset($map[$operation])) {
      return AccessResult::neutral();
    }

    $cid = implode(':', [
      $group->getRevisionId(),
      $group->language()->getId(),
      $account->id(),
      $operation,
    ]);

    if (isset($this->accessCache[$cid])) {
      return $this->accessCache[$cid];
    }

    // You cannot manipulate the default revision through the revision UI.
    if ($operation !== 'view' && $group->isDefaultRevision()) {
      $this->accessCache[$cid] = AccessResult::forbidden()->addCacheableDependency($group);
      return $this->accessCache[$cid];
    }

    // You cannot view the default revision through the revision UI if it is
    // the only revision available and the group type is not set to create new
    // revisions. The latter is checked because if it was set, we might allow
    // access so the revisions tab shows up.
    $group_type = $group->getGroupType();
    if ($operation === 'view' && $group->isDefaultRevision() && $this->countDefaultLanguageRevisions($group) === 1 && !$group_type->shouldCreateNewRevision()) {
      $this->accessCache[$cid] = AccessResult::forbidden()->addCacheableDependency($group_type)->addCacheableDependency($group);
      return $this->accessCache[$cid];
    }

    // Perform basic permission checks first and return no-access results.
    $this->accessCache[$cid] = GroupAccessResult::allowedIfHasGroupPermission($group, $account, $map[$operation]);
    if (!$this->accessCache[$cid]->isAllowed()) {
      return $this->accessCache[$cid];
    }

    // Now that the edge cases are out of the way, check for entity access on
    // both the default revision and the passed in revision (if any).
    $entity_access = $group->access($operation, $account, TRUE);
    if (!$group->isDefaultRevision()) {
      $default_revision = $this->entityTypeManager->getStorage('group')->load($group->id());
      $entity_access = $entity_access->andIf($default_revision->access($operation, $account, TRUE));
    }
    $this->accessCache[$cid] = $this->accessCache[$cid]->andIf($entity_access);

    // Because of the group type checks above when dealing with the 'view'
    // operation, we must add the group type as a cacheable dependency so we can
    // return a different result in case shouldCreateNewRevision() changes.
    if ($operation === 'view') {
      $this->accessCache[$cid] = $this->accessCache[$cid]->addCacheableDependency($group_type);
    }

    return $this->accessCache[$cid];
  }

  /**
   * Counts the number of revisions in the default language.
   *
   * @param \Drupal\group\Entity\GroupInterface $group
   *   The group.
   *
   * @return int
   *   The number of revisions in the default language.
   */
  protected function countDefaultLanguageRevisions(GroupInterface $group) {
    return (int) $this->entityTypeManager->getStorage('group')
      ->getQuery()
      ->allRevisions()
      ->accessCheck(FALSE)
      ->condition('id', $group->id())
      ->condition('default_langcode', 1)
      ->count()
      ->execute();
  }

}
