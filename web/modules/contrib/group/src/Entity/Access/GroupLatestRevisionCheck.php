<?php

namespace Drupal\group\Entity\Access;

use Drupal\content_moderation\ModerationInformationInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\group\Access\GroupAccessResult;
use Symfony\Component\Routing\Route;

/**
 * Access check for the group moderation tab.
 */
class GroupLatestRevisionCheck implements AccessInterface {

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The moderation information service.
   *
   * @var \Drupal\content_moderation\ModerationInformationInterface
   */
  protected $moderationInfo;

  /**
   * Constructs a GroupLatestRevisionCheck object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * Set moderation info.
   *
   * @param \Drupal\content_moderation\ModerationInformationInterface $moderation_information
   *   The moderation information service.
   */
  public function setContentModerationInfo(ModerationInformationInterface $moderation_information) {
    $this->moderationInfo = $moderation_information;
  }

  /**
   * Checks that there is a pending revision available.
   *
   * This checker assumes the presence of an '_entity_access' requirement key
   * in the same form as used by EntityAccessCheck.
   *
   * @param \Symfony\Component\Routing\Route $route
   *   The route to check against.
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The parametrized route.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The current user account.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   *
   * @see \Drupal\Core\Entity\EntityAccessCheck
   */
  public function access(Route $route, RouteMatchInterface $route_match, AccountInterface $account) {
    /** @var \Drupal\group\Entity\GroupInterface $group */
    $group = $route_match->getParameter('group');

    // This tab should not show up unless there's a reason to show it.
    if (!$this->moderationInfo->hasPendingRevision($group)) {
      return AccessResult::forbidden('No pending revision for this group.')->addCacheableDependency($group);
    }

    // Unlike Drupal core, we allow revision viewing if you have 'view' access
    // to the group along with the 'view latest group version' group permission.
    // This allows access modules to have more say over who can view revisions,
    // rather than having to swap out this class to add permissions.
    //
    // See core issue: https://www.drupal.org/project/drupal/issues/2943471.
    $storage = $this->entityTypeManager->getStorage('group');
    $group_revision = $storage->loadRevision(
      $storage->getLatestTranslationAffectedRevisionId($group->id(), $group->language()->getId())
    );

    return GroupAccessResult::allowedIfHasGroupPermission($group, $account, 'view latest group version')
      ->andIf($group_revision->access('view', $account, TRUE));
  }

}
