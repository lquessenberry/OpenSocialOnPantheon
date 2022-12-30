<?php

namespace Drupal\group\QueryAccess;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Query\SelectInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityPublishedInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Sql\SqlContentEntityStorage;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\group\Access\CalculatedGroupPermissionsItemInterface as CGPII;
use Drupal\group\Access\ChainGroupPermissionCalculatorInterface;
use Drupal\group\Plugin\GroupContentEnablerManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Defines a class for altering entity queries.
 *
 * If we use query_access handlers for grouped entities, we would need to add a
 * '{id_key} NOT IN (:all_grouped_entity_ids)' condition. With sites that have
 * thousands or more grouped entities, this is not sustainable. Instead we alter
 * the query directly to join the group_content_field_data table.
 *
 * @internal
 */
class EntityQueryAlter implements ContainerInjectionInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The group content enabler plugin manager.
   *
   * @var \Drupal\group\Plugin\GroupContentEnablerManagerInterface
   */
  protected $pluginManager;

  /**
   * The group permission calculator.
   *
   * @var \Drupal\group\Access\GroupPermissionCalculatorInterface
   */
  protected $permissionCalculator;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * The renderer.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * The query cacheable metadata.
   *
   * @var \Drupal\Core\Cache\CacheableMetadata
   */
  protected $cacheableMetadata;

  /**
   * The data table alias.
   *
   * @var string|false
   */
  protected $dataTableAlias = FALSE;

  /**
   * Constructs a new EntityQueryAlter object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\group\Plugin\GroupContentEnablerManagerInterface $plugin_manager
   *   The group content enabler plugin manager.
   * @param \Drupal\group\Access\ChainGroupPermissionCalculatorInterface $permission_calculator
   *   The group permission calculator.
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, GroupContentEnablerManagerInterface $plugin_manager, ChainGroupPermissionCalculatorInterface $permission_calculator, Connection $database, RendererInterface $renderer, RequestStack $request_stack, AccountInterface $current_user) {
    $this->entityTypeManager = $entity_type_manager;
    $this->pluginManager = $plugin_manager;
    $this->permissionCalculator = $permission_calculator;
    $this->database = $database;
    $this->renderer = $renderer;
    $this->requestStack = $request_stack;
    $this->currentUser = $current_user;
    $this->cacheableMetadata = new CacheableMetadata();
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('plugin.manager.group_content_enabler'),
      $container->get('group_permission.chain_calculator'),
      $container->get('database'),
      $container->get('renderer'),
      $container->get('request_stack'),
      $container->get('current_user')
    );
  }

  /**
   * Alters the select query for the given entity type.
   *
   * @param \Drupal\Core\Database\Query\SelectInterface $query
   *   The select query.
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type.
   */
  public function alter(SelectInterface $query, EntityTypeInterface $entity_type) {
    $this->doAlter($query, $entity_type, $query->getMetaData('op') ?: 'view');
    $this->applyCacheability();
  }

  /**
   * Actually alters the select query for the given entity type.
   *
   * @param \Drupal\Core\Database\Query\SelectInterface $query
   *   The select query.
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type.
   * @param string $operation
   *   The query operation.
   */
  protected function doAlter(SelectInterface $query, EntityTypeInterface $entity_type, $operation) {
    $entity_type_id = $entity_type->id();
    $storage = $this->entityTypeManager->getStorage($entity_type_id);
    if (!$storage instanceof SqlContentEntityStorage) {
      return;
    }

    // Find all of the group content plugins that define access.
    $plugin_ids = $this->pluginManager->getPluginIdsByEntityTypeAccess($entity_type_id);
    if (empty($plugin_ids)) {
      return;
    }

    // Load all of the group content types that define access.
    /** @var \Drupal\group\Entity\Storage\GroupContentTypeStorageInterface $gct_storage */
    $gct_storage = $this->entityTypeManager->getStorage('group_content_type');
    $group_content_types = $gct_storage->loadByContentPluginId($plugin_ids);

    // If any new group content entity is added using any of the retrieved
    // plugins, it might change access.
    $cache_tags = [];
    foreach ($plugin_ids as $plugin_id) {
      $cache_tags[] = "group_content_list:plugin:$plugin_id";
    }
    $this->cacheableMetadata->addCacheTags($cache_tags);

    if (empty($group_content_types)) {
      // Because we add cache tags checking for new group content above, we can
      // simply bail out here without adding any group content type related
      // cache tags because a new group content type does not change the
      // permissions until a group content is created using said group content
      // type, at which point the cache tags above kick in.
      return;
    }

    // Find all group content types that have content for them.
    $group_content_type_ids_in_use = $this->database
      ->select('group_content_field_data', 'gc')
      ->fields('gc', ['type'])
      ->condition('type', array_keys($group_content_types), 'IN')
      ->distinct()
      ->execute()
      ->fetchCol();

    if (empty($group_content_type_ids_in_use)) {
      return;
    }

    // @todo Remove these lines once we kill the bypass permission.
    // If the account can bypass group access, we do not alter the query at all.
    $this->cacheableMetadata->addCacheContexts(['user.permissions']);
    if ($this->currentUser->hasPermission('bypass group access')) {
      return;
    }

    // From this point onward, we know that there are grouped entities and that
    // we need to check access, so we can LEFT JOIN the necessary table.
    $base_table = $entity_type->getBaseTable();
    $id_key = $entity_type->getKey('id');

    // The base table is usually aliased, so let's try and find it.
    foreach ($query->getTables() as $alias => $table) {
      if ($table['join type'] === NULL) {
        $base_table = $alias;
        break;
      }
    }
    $query->leftJoin(
      'group_content_field_data',
      'gcfd',
      "$base_table.$id_key=gcfd.entity_id AND gcfd.type IN (:group_content_type_ids_in_use[])",
      [':group_content_type_ids_in_use[]' => $group_content_type_ids_in_use]
    );

    $this->cacheableMetadata->addCacheContexts(['user.group_permissions']);
    $calculated_permissions = $this->permissionCalculator->calculatePermissions($this->currentUser);

    // We only check unpublished vs published for "view" right now. If we ever
    // start supporting other operations, we need to remove the "view" check.
    $check_published = $operation === 'view' && $entity_type->entityClassImplements(EntityPublishedInterface::class);

    $owner_key = $entity_type->getKey('owner');
    $published_key = $entity_type->getKey('published');

    // Get some maps to use in the loops below so we save some milliseconds.
    $plugin_id_map = $this->pluginManager->getPluginGroupContentTypeMap();
    $gct_to_gt_map = [];
    foreach ($group_content_types as $group_content_type_id => $group_content_type) {
      $gct_to_gt_map[$group_content_type_id] = $group_content_type->getGroupTypeId();
    }

    $allowed_any_ids = $allowed_own_ids = $allowed_any_by_status_ids = $allowed_own_by_status_ids = $member_group_ids = [];
    foreach ($plugin_ids as $plugin_id) {
      // If the plugin is not installed, skip it.
      if (!isset($plugin_id_map[$plugin_id])) {
        continue;
      }

      // For backwards compatibility reasons, if the group content enabler
      // plugin used by the group content type does not specify a permission
      // provider, we do not alter the query for that group content type. In
      // 8.2.x all group content types will get a permission handler by
      // default, so this check can be safely removed then.
      if (!$this->pluginManager->hasHandler($plugin_id, 'permission_provider')) {
        continue;
      }

      foreach ($plugin_id_map[$plugin_id] as $group_content_type_id) {
        // If the group content type has no content, skip it.
        if (!in_array($group_content_type_id, $group_content_type_ids_in_use)) {
          continue;
        }

        $handler = $this->pluginManager->getPermissionProvider($plugin_id);
        $admin_permission = $handler->getAdminPermission();
        $any_permission = $handler->getPermission($operation, 'entity', 'any');
        $own_permission = $handler->getPermission($operation, 'entity', 'own');
        if ($check_published) {
          $any_unpublished_permission = $handler->getPermission("$operation unpublished", 'entity', 'any');
          $own_unpublished_permission = $handler->getPermission("$operation unpublished", 'entity', 'own');
        }

        foreach ($calculated_permissions->getItems() as $item) {
          // For groups, we need to get the group ID to add to the query.
          $identifier = 'INVALID';
          if ($item->getScope() === CGPII::SCOPE_GROUP) {
            $identifier = $item->getIdentifier();
            $member_group_ids[] = $identifier;
          }
          // For group types, we need to use the group content type ID to limit
          // the query results, but we also need to make sure that the scope ID
          // actually represents the current group content type ID.
          elseif ($item->getScope() === CGPII::SCOPE_GROUP_TYPE) {
            if ($gct_to_gt_map[$group_content_type_id] !== $item->getIdentifier()) {
              continue;
            }
            $identifier = $group_content_type_id;
          }

          if ($admin_permission !== FALSE && $item->hasPermission($admin_permission)) {
            $allowed_any_ids[$item->getScope()][] = $identifier;
          }
          elseif(!$check_published) {
            if ($any_permission !== FALSE && $item->hasPermission($any_permission)) {
              $allowed_any_ids[$item->getScope()][] = $identifier;
            }
            elseif($own_permission !== FALSE && $item->hasPermission($own_permission)) {
              $allowed_own_ids[$item->getScope()][] = $identifier;
            }
          }
          else {
            if ($any_permission !== FALSE && $item->hasPermission($any_permission)) {
              $allowed_any_by_status_ids[$item->getScope()][1][] = $identifier;
            }
            elseif($own_permission !== FALSE && $item->hasPermission($own_permission)) {
              $allowed_own_by_status_ids[$item->getScope()][1][] = $identifier;
            }
            if ($any_unpublished_permission !== FALSE && $item->hasPermission($any_unpublished_permission)) {
              $allowed_any_by_status_ids[$item->getScope()][0][] = $identifier;
            }
            elseif($own_unpublished_permission !== FALSE && $item->hasPermission($own_unpublished_permission)) {
              $allowed_own_by_status_ids[$item->getScope()][0][] = $identifier;
            }
          }
        }
      }
    }

    // If no group type or group gave access, we deny access altogether.
    if (empty($allowed_any_ids) && empty($allowed_own_ids) && empty($allowed_any_by_status_ids) && empty($allowed_own_by_status_ids)) {
      $query->isNull('gcfd.entity_id');
      return;
    }

    // From this point on, we know there is something that will allow access, so
    // we need to alter the query to check that entity_id is null or the group
    // access checks apply.
    $query->condition(
      $query->orConditionGroup()
        ->isNull('gcfd.entity_id')
        ->condition($group_conditions = $query->orConditionGroup())
    );

    // We might see multiple values in the $member_group_ids variable because we
    // looped over all calculated permissions multiple times.
    if (!empty($member_group_ids)) {
      $member_group_ids = array_unique($member_group_ids);
    }

    // Add the allowed group types to the query (if any).
    if (!empty($allowed_any_ids[CGPII::SCOPE_GROUP_TYPE])) {
      $sub_condition = $query->andConditionGroup();
      $sub_condition->condition('gcfd.type', array_unique($allowed_any_ids[CGPII::SCOPE_GROUP_TYPE]), 'IN');

      // If the user had memberships, we need to make sure they are excluded
      // from group type based matches as the memberships' permissions take
      // precedence.
      if (!empty($member_group_ids)) {
        $sub_condition->condition('gcfd.gid', $member_group_ids, 'NOT IN');
      }

      $group_conditions->condition($sub_condition);
    }

    // Add the memberships with access to the query (if any).
    if (!empty($allowed_any_ids[CGPII::SCOPE_GROUP])) {
      $group_conditions->condition('gcfd.gid', array_unique($allowed_any_ids[CGPII::SCOPE_GROUP]), 'IN');
    }

    // In order to define query access for grouped entities and at the same time
    // leave the ungrouped alone, we need allow access to all entities that:
    // - Do not belong to a group.
    // - Belong to a group and to which:
    //   - The user has any access.
    //   - The user has owner access and is the owner of.
    //
    // In case the entity supports publishing, the last condition is swapped out
    // for the following two:
    // - The entity is published and:
    //   - The user has any access.
    //   - The user has owner access and is the owner of.
    // - The entity is unpublished and:
    //   - The user has any access.
    //   - The user has owner access and is the owner of.
    //
    // In any case, the first two conditions are always the same and have been
    // added above already.
    //
    // From this point we need to either find the entities the user can access
    // as the owner or the entities accessible as both the owner and non-owner
    // when the entity supports publishing.
    if (!$check_published) {
      // Nothing gave owner access so bail out entirely.
      if (empty($allowed_own_ids[CGPII::SCOPE_GROUP_TYPE]) && empty($allowed_own_ids[CGPII::SCOPE_GROUP])) {
        return;
      }
      $this->cacheableMetadata->addCacheContexts(['user']);

      $data_table = $this->ensureDataTable($base_table, $query, $entity_type);
      $group_conditions->condition(
        $query->andConditionGroup()
          ->condition("$data_table.$owner_key", $this->currentUser->id())
          ->condition($owner_group_conditions = $query->orConditionGroup())
      );

      // Add the allowed owner group types to the query (if any).
      if (!empty($allowed_own_ids[CGPII::SCOPE_GROUP_TYPE])) {
        $sub_condition = $query->andConditionGroup();
        $sub_condition->condition('gcfd.type', array_unique($allowed_own_ids[CGPII::SCOPE_GROUP_TYPE]), 'IN');

        // If the user had memberships, we need to make sure they are excluded
        // from group type based matches as the memberships' permissions take
        // precedence.
        if (!empty($member_group_ids)) {
          $sub_condition->condition('gcfd.gid', $member_group_ids, 'NOT IN');
        }

        $owner_group_conditions->condition($sub_condition);
      }

      // Add the owner memberships with access to the query (if any).
      if (!empty($allowed_own_ids[CGPII::SCOPE_GROUP])) {
        $owner_group_conditions->condition('gcfd.gid', array_unique($allowed_own_ids[CGPII::SCOPE_GROUP]), 'IN');
      }
    }
    else {
      foreach ([0, 1] as $status) {
        // Nothing gave owner access so bail out entirely.
        if (empty($allowed_any_by_status_ids[CGPII::SCOPE_GROUP_TYPE][$status])
          && empty($allowed_any_by_status_ids[CGPII::SCOPE_GROUP][$status])
          && empty($allowed_own_by_status_ids[CGPII::SCOPE_GROUP_TYPE][$status])
          && empty($allowed_own_by_status_ids[CGPII::SCOPE_GROUP][$status])
        ) {
          continue;
        }

        $data_table = $this->ensureDataTable($base_table, $query, $entity_type);
        $group_conditions->condition(
          $query->andConditionGroup()
            ->condition("$data_table.$published_key", $status)
            ->condition($status_group_conditions = $query->orConditionGroup())
        );

        // Add the allowed group types to the query (if any).
        if (!empty($allowed_any_by_status_ids[CGPII::SCOPE_GROUP_TYPE][$status])) {
          $sub_condition = $query->andConditionGroup();
          $sub_condition->condition('gcfd.type', array_unique($allowed_any_by_status_ids[CGPII::SCOPE_GROUP_TYPE][$status]), 'IN');

          // If the user had memberships, we need to make sure they are excluded
          // from group type based matches as the memberships' permissions take
          // precedence.
          if (!empty($member_group_ids)) {
            $sub_condition->condition('gcfd.gid', $member_group_ids, 'NOT IN');
          }

          $status_group_conditions->condition($sub_condition);
        }

        // Add the memberships with access to the query (if any).
        if (!empty($allowed_any_by_status_ids[CGPII::SCOPE_GROUP][$status])) {
          $status_group_conditions->condition('gcfd.gid', array_unique($allowed_any_by_status_ids[CGPII::SCOPE_GROUP][$status]), 'IN');
        }

        // Nothing gave owner access so try the next publication status.
        if (empty($allowed_own_by_status_ids[CGPII::SCOPE_GROUP_TYPE][$status]) && empty($allowed_own_by_status_ids[CGPII::SCOPE_GROUP][$status])) {
          continue;
        }
        $this->cacheableMetadata->addCacheContexts(['user']);

        $status_group_conditions->condition(
          $query->andConditionGroup()
            ->condition("$data_table.$owner_key", $this->currentUser->id())
            ->condition($status_owner_group_conditions = $query->orConditionGroup())
        );

        // Add the allowed owner group types to the query (if any).
        if (!empty($allowed_own_by_status_ids[CGPII::SCOPE_GROUP_TYPE][$status])) {
          $sub_condition = $query->andConditionGroup();
          $sub_condition->condition('gcfd.type', array_unique($allowed_own_by_status_ids[CGPII::SCOPE_GROUP_TYPE][$status]), 'IN');

          // If the user had memberships, we need to make sure they are excluded
          // from group type based matches as the memberships' permissions take
          // precedence.
          if (!empty($member_group_ids)) {
            $sub_condition->condition('gcfd.gid', $member_group_ids, 'NOT IN');
          }

          $status_owner_group_conditions->condition($sub_condition);
        }

        // Add the owner memberships with access to the query (if any).
        if (!empty($allowed_own_by_status_ids[CGPII::SCOPE_GROUP][$status])) {
          $status_owner_group_conditions->condition('gcfd.gid', array_unique($allowed_own_by_status_ids[CGPII::SCOPE_GROUP][$status]) , 'IN');
        }
      }
    }
  }

  /**
   * Ensures the query is joined against the data table.
   *
   * @param string $base_table
   *   The alias of the base table.
   * @param \Drupal\Core\Database\Query\SelectInterface $query
   *   The select query.
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type.
   *
   * @return string
   *   The data table alias.
   */
  protected function ensureDataTable($base_table, SelectInterface $query, EntityTypeInterface $entity_type) {
    if ($this->dataTableAlias === FALSE) {
      if (!$data_table = $entity_type->getDataTable()) {
        $data_table = $base_table;
        $data_table_found = TRUE;
      }
      else {
        $data_table_found = FALSE;

        foreach ($query->getTables() as $alias => $table) {
          if (!$data_table_found && ($table['join type'] === 'INNER' || $alias === $base_table) && $table['table'] === $data_table) {
            $data_table = $alias;
            $data_table_found = TRUE;
            break;
          }
        }
      }

      // If the data table wasn't added to the query yet, add it here.
      if (!$data_table_found) {
        $id_key = $entity_type->getKey('id');
        $this->dataTableAlias = $query->join($data_table, $data_table, "$base_table.$id_key=$data_table.$id_key");
      }
      else {
        $this->dataTableAlias = $data_table;
      }
    }

    return $this->dataTableAlias;
  }

  /**
   * Applies the cacheablity metadata to the current request.
   */
  protected function applyCacheability() {
    $request = $this->requestStack->getCurrentRequest();
    if ($request->isMethodCacheable() && $this->renderer->hasRenderContext() && $this->hasCacheableMetadata()) {
      $build = [];
      $this->cacheableMetadata->applyTo($build);
      $this->renderer->render($build);
    }
  }

  /**
   * Check if the cacheable metadata is not empty.
   *
   * An empty cacheable metadata object has no context, tags, and is permanent.
   *
   * @return bool
   *   TRUE if there is cacheability metadata, otherwise FALSE.
   */
  protected function hasCacheableMetadata() {
    return $this->cacheableMetadata->getCacheMaxAge() !== Cache::PERMANENT
      || count($this->cacheableMetadata->getCacheContexts()) > 0
      || count($this->cacheableMetadata->getCacheTags()) > 0;
  }

}
