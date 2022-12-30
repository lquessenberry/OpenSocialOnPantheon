<?php

namespace Drupal\group\Cache\Context;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Cache\Context\CalculatedCacheContextInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\group\GroupMembershipLoaderInterface;

/**
 * Defines a cache context for "is a group member or not" caching.
 *
 * Do NOT use this on an element that shows up a lot, but with a different group
 * ID depending on the situation. E.g.: The group operations block. The reason
 * is that core will combine all of these possible outcomes into a really long
 * cache key and both CacheContextsManager and RenderCache will have to run code
 * for each and every one of the possible outcomes.
 *
 * You MAY, however, use this for objects that almost always ask for the same
 * group ID. A good example would be a call-to-action on some pages that only
 * shows up if you're not a member of a specific group yet. In that scenario,
 * you'll definitely want to use this cache context and it will not kill your
 * site's performance with fire.
 *
 * Calculated cache context ID: 'user.is_group_member:%group_id'.
 *
 * @todo With the new VariationCache's CacheRedirect system, this could actually
 * be used on the GroupOperationsBlock as it no longer expands into a really
 * long cache ID but simply adds another CacheRedirect.
 */
class IsGroupMemberCacheContext implements CalculatedCacheContextInterface {

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The membership loader service.
   *
   * @var \Drupal\group\GroupMembershipLoaderInterface
   */
  protected $membershipLoader;

  /**
   * Constructs a new GroupMembershipPermissionsCacheContext class.
   *
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   The current user.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\group\GroupMembershipLoaderInterface $membership_loader
   *   The group membership loader service.
   */
  public function __construct(AccountProxyInterface $current_user, EntityTypeManagerInterface $entity_type_manager, GroupMembershipLoaderInterface $membership_loader) {
    $this->currentUser = $current_user;
    $this->entityTypeManager = $entity_type_manager;
    $this->membershipLoader = $membership_loader;
  }

  /**
   * {@inheritdoc}
   */
  public static function getLabel() {
    return t("Is group member");
  }

  /**
   * {@inheritdoc}
   */
  public function getContext($group_id = NULL) {
    if (!$group_id) {
      throw new \LogicException('No group ID provided for user.is_group_member cache context.');
    }

    $group = $this->entityTypeManager->getStorage('group')->load($group_id);
    if (!$group) {
      throw new \LogicException('Incorrect group ID provided for user.is_group_member cache context.');
    }

    return $this->membershipLoader->load($group, $this->currentUser) ? '1' : '0';
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheableMetadata($group_id = NULL) {
    if (!$group_id) {
      throw new \LogicException('No group ID provided for user.is_group_member cache context.');
    }

    // The value of this context is affected when the user joins or leaves the
    // group. Both of which trigger a user save, so we can simply add the user's
    // cacheable metadata here.
    return CacheableMetadata::createFromObject($this->currentUser->getAccount());
  }

}
