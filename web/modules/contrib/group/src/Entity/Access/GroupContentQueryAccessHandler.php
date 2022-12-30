<?php

namespace Drupal\group\Entity\Access;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\entity\QueryAccess\ConditionGroup;
use Drupal\group\Access\CalculatedGroupPermissionsItemInterface as CGPII;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Controls query access for group_content entities.
 *
 * @see \Drupal\entity\QueryAccess\QueryAccessHandler
 */
class GroupContentQueryAccessHandler extends QueryAccessHandlerBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The group content enabler manager.
   *
   * @var \Drupal\group\Plugin\GroupContentEnablerManagerInterface
   */
  protected $groupContentEnablerManager;

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    /** @var static $instance */
    $instance = parent::createInstance($container, $entity_type);
    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->groupContentEnablerManager = $container->get('plugin.manager.group_content_enabler');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  protected function buildConditions($operation, AccountInterface $account) {
    $conditions = new ConditionGroup('OR');

    // @todo Remove these lines once we kill the bypass permission.
    // If the account can bypass group access, we do not alter the query at all.
    $conditions->addCacheContexts(['user.permissions']);
    if ($account->hasPermission('bypass group access')) {
      return $conditions;
    }

    $conditions->addCacheContexts(['user.group_permissions']);
    $calculated_permissions = $this->groupPermissionCalculator->calculatePermissions($account);
    $group_permissions = $calculated_permissions->getItemsByScope(CGPII::SCOPE_GROUP);

    /** @var \Drupal\group\Entity\GroupContentTypeInterface[] $group_content_types */
    $group_content_types = $this->entityTypeManager->getStorage('group_content_type')->loadMultiple();

    $allowed_any_ids = $allowed_own_ids = $all_ids = [];
    foreach ($group_content_types as $group_content_type_id => $group_content_type) {
      $plugin_id = $group_content_type->getContentPluginId();

      // For backwards compatibility reasons, if the group content enabler
      // plugin used by the group content type does not specify a permission
      // provider, we do not alter the query for that group content type. In
      // 8.2.x all group content types will get a permission handler by default,
      // so this check can be safely removed then.
      if (!$this->groupContentEnablerManager->hasHandler($plugin_id, 'permission_provider')) {
        continue;
      }
      $handler = $this->groupContentEnablerManager->getPermissionProvider($plugin_id);
      $admin_permission = $handler->getAdminPermission();
      $any_permission = $handler->getPermission($operation, 'relation', 'any');
      $own_permission = $handler->getPermission($operation, 'relation', 'own');

      // For each iteration, we can check all group items because it's still
      // faster than checking the DB for which Group ID belongs to which group
      // content type. We will add in the group-type scope per group content type.
      $applicable_permissions = array_merge(
        $group_permissions,
        [$calculated_permissions->getItem(CGPII::SCOPE_GROUP_TYPE, $group_content_type->getGroupTypeId())]
      );

      foreach ($applicable_permissions as $item) {
        $all_ids[$item->getScope()][] = $item->getIdentifier();

        // For groups, we need to get the group ID, but for group types, we need
        // to use the group content type ID rather than the group type ID.
        $identifier = $item->getScope() === CGPII::SCOPE_GROUP
          ? $item->getIdentifier()
          : $group_content_type_id;

        if ($admin_permission !== FALSE && $item->hasPermission($admin_permission)) {
          $allowed_any_ids[$item->getScope()][] = $identifier;
        }
        elseif ($any_permission !== FALSE && $item->hasPermission($any_permission)) {
          $allowed_any_ids[$item->getScope()][] = $identifier;
        }
        elseif($own_permission !== FALSE && $item->hasPermission($own_permission)) {
          $allowed_own_ids[$item->getScope()][] = $identifier;
        }
      }
    }

    // If no group type or group gave access, we deny access altogether.
    if (empty($allowed_any_ids) && empty($allowed_own_ids)) {
      $conditions->alwaysFalse();
      return $conditions;
    }

    // We might see multiple values in the $all_ids variable because we looped
    // over all calculated permissions multiple times.
    if (!empty($all_ids[CGPII::SCOPE_GROUP])) {
      $all_ids[CGPII::SCOPE_GROUP] = array_unique($all_ids[CGPII::SCOPE_GROUP]);
    }

    // Add the allowed group types to the query (if any).
    if (!empty($allowed_any_ids[CGPII::SCOPE_GROUP_TYPE])) {
      $sub_condition = new ConditionGroup();
      $sub_condition->addCondition('type', $allowed_any_ids[CGPII::SCOPE_GROUP_TYPE]);

      // If the user had memberships, we need to make sure they are excluded
      // from group type based matches as the memberships' permissions take
      // precedence.
      if (!empty($all_ids[CGPII::SCOPE_GROUP])) {
        $sub_condition->addCondition('gid', $all_ids[CGPII::SCOPE_GROUP], 'NOT IN');
      }

      $conditions->addCondition($sub_condition);
    }

    // Add the allowed owner group types to the query (if any).
    if (!empty($allowed_own_ids[CGPII::SCOPE_GROUP_TYPE])) {
      $sub_condition = new ConditionGroup();
      $sub_condition->addCondition('uid', $account->id());
      $sub_condition->addCondition('type', $allowed_own_ids[CGPII::SCOPE_GROUP_TYPE]);

      // If the user had memberships, we need to make sure they are excluded
      // from group type based matches as the memberships' permissions take
      // precedence.
      if (!empty($all_ids[CGPII::SCOPE_GROUP])) {
        $sub_condition->addCondition('gid', $all_ids[CGPII::SCOPE_GROUP], 'NOT IN');
      }

      $conditions->addCacheContexts(['user']);
      $conditions->addCondition($sub_condition);
    }

    // Add the memberships with access to the query (if any).
    if (!empty($allowed_any_ids[CGPII::SCOPE_GROUP])) {
      $conditions->addCondition('gid', array_unique($allowed_any_ids[CGPII::SCOPE_GROUP]));
    }

    // Add the owner memberships with access to the query (if any).
    if (!empty($allowed_own_ids[CGPII::SCOPE_GROUP])) {
      $sub_condition = new ConditionGroup();
      $sub_condition->addCondition('uid', $account->id());
      $sub_condition->addCondition('gid', array_unique($allowed_own_ids[CGPII::SCOPE_GROUP]));

      $conditions->addCacheContexts(['user']);
      $conditions->addCondition($sub_condition);
    }

    return $conditions;
  }

}
