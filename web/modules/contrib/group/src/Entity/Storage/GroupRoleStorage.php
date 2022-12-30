<?php

namespace Drupal\group\Entity\Storage;

use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\Cache\MemoryCache\MemoryCacheInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\Entity\ConfigEntityStorage;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\group\Entity\GroupInterface;
use Drupal\group\GroupMembershipLoaderInterface;
use Drupal\group\GroupRoleSynchronizerInterface;
use Drupal\User\RoleInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines the storage handler class for group role entities.
 *
 * This extends the base storage class, adding required special handling for
 * loading group role entities based on user and group information.
 */
class GroupRoleStorage extends ConfigEntityStorage implements GroupRoleStorageInterface {

  /**
   * Static cache of a user's group role IDs.
   *
   * @var array
   */
  protected $userGroupRoleIds = [];

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The group role synchronizer service.
   *
   * @var \Drupal\group\GroupRoleSynchronizerInterface
   */
  protected $groupRoleSynchronizer;

  /**
   * The group membership loader.
   *
   * @var \Drupal\group\GroupMembershipLoaderInterface
   */
  protected $groupMembershipLoader;

  /**
   * Constructs a GroupRoleStorage object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\group\GroupRoleSynchronizerInterface $group_role_synchronizer
   *   The group role synchronizer service.
   * @param \Drupal\group\GroupMembershipLoaderInterface $group_membership_loader
   *   The group membership loader.
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type definition.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory service.
   * @param \Drupal\Component\Uuid\UuidInterface $uuid_service
   *   The UUID service.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager.
   * @param \Drupal\Core\Cache\MemoryCache\MemoryCacheInterface $memory_cache
   *   The memory cache backend.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, GroupRoleSynchronizerInterface $group_role_synchronizer, GroupMembershipLoaderInterface $group_membership_loader, EntityTypeInterface $entity_type, ConfigFactoryInterface $config_factory, UuidInterface $uuid_service, LanguageManagerInterface $language_manager, MemoryCacheInterface $memory_cache) {
    parent::__construct($entity_type, $config_factory, $uuid_service, $language_manager, $memory_cache);
    $this->entityTypeManager = $entity_type_manager;
    $this->groupRoleSynchronizer = $group_role_synchronizer;
    $this->groupMembershipLoader = $group_membership_loader;
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('group_role.synchronizer'),
      $container->get('group.membership_loader'),
      $entity_type,
      $container->get('config.factory'),
      $container->get('uuid'),
      $container->get('language_manager'),
      $container->get('entity.memory_cache')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function loadByUserAndGroup(AccountInterface $account, GroupInterface $group, $include_implied = TRUE) {
    $uid = $account->id();
    $gid = $group->id();
    $key = $include_implied ? 'include' : 'exclude';

    if (!isset($this->userGroupRoleIds[$uid][$gid][$key])) {
      $ids = [];

      // Get the IDs from the 'group_roles' field, without loading the roles.
      if ($membership = $this->groupMembershipLoader->load($group, $account)) {
        foreach ($membership->getGroupContent()->group_roles as $group_role_ref) {
          $ids[] = $group_role_ref->target_id;
        }
      }

      // Add the implied group role IDs.
      if ($include_implied) {
        $group_type = $group->getGroupType();

        if ($membership !== FALSE) {
          $ids[] = $group_type->getMemberRoleId();
        }
        elseif ($account->isAnonymous()) {
          $ids[] = $group_type->getAnonymousRoleId();
        }
        else {
          $ids[] = $group_type->getOutsiderRoleId();
          foreach ($account->getRoles(TRUE) as $role_id) {
            $ids[] = $this->groupRoleSynchronizer->getGroupRoleId($group_type->id(), $role_id);
          }
        }
      }

      $this->userGroupRoleIds[$uid][$gid][$key] = $ids;
    }

    return $this->loadMultiple($this->userGroupRoleIds[$uid][$gid][$key]);
  }

  /**
   * {@inheritdoc}
   */
  public function loadSynchronizedByGroupTypes(array $group_type_ids) {
    return $this->loadMultiple($this->groupRoleSynchronizer->getGroupRoleIdsByGroupTypes($group_type_ids));
  }

  /**
   * {@inheritdoc}
   */
  public function loadSynchronizedByUserRoles(array $role_ids) {
    return $this->loadMultiple($this->groupRoleSynchronizer->getGroupRoleIdsByUserRoles($role_ids));
  }

  /**
   * {@inheritdoc}
   */
  public function createInternal($group_type_ids = NULL) {
    /** @var \Drupal\group\Entity\GroupTypeInterface[] $group_types */
    $group_types = $this->entityTypeManager->getStorage('group_type')->loadMultiple($group_type_ids);

    // Return early if there are no group types to create roles for.
    if (empty($group_types)) {
      return;
    }

    $definitions = [];
    foreach ($group_types as $group_type_id => $group_type) {
      // Build a list of group role definitions but do not save them yet so we
      // can check whether they already exist in bulk instead of trying to find
      // out on an individual basis here.
      $definitions[$group_type->getAnonymousRoleId()] = [
        'id' => $group_type->getAnonymousRoleId(),
        'label' => $this->t('Anonymous'),
        'weight' => -102,
        'internal' => TRUE,
        'audience' => 'anonymous',
        'group_type' => $group_type_id,
      ];

      $definitions[$group_type->getOutsiderRoleId()] = [
        'id' => $group_type->getOutsiderRoleId(),
        'label' => $this->t('Outsider'),
        'weight' => -101,
        'internal' => TRUE,
        'audience' => 'outsider',
        'group_type' => $group_type_id,
      ];

      $definitions[$group_type->getMemberRoleId()] = [
        'id' => $group_type->getMemberRoleId(),
        'label' => $this->t('Member'),
        'weight' => -100,
        'internal' => TRUE,
        'group_type' => $group_type_id,
      ];
    }

    // See if the roles we just defined already exist.
    $query = $this->getQuery();
    $query->condition('id', array_keys($definitions));

    // Create the group roles that do not exist yet.
    foreach (array_diff_key($definitions, $query->execute()) as $definition) {
      $this->save($this->create($definition));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function createSynchronized($group_type_ids = NULL, $role_ids = NULL) {
    // Load all possible group type IDs if none were provided.
    if (empty($group_type_ids)) {
      $group_type_ids = $this->entityTypeManager->getStorage('group_type')->getQuery()->execute();
    }

    // Return early if there are no group types to create roles for.
    if (empty($group_type_ids)) {
      return;
    }

    /** @var \Drupal\User\RoleInterface[] $user_roles */
    $user_roles = $this->entityTypeManager->getStorage('user_role')->loadMultiple($role_ids);

    $definitions = [];
    foreach (array_keys($user_roles) as $role_id) {
      // We do not synchronize the 'anonymous' or 'authenticated' user roles as
      // they are already taken care of by the 'anonymous' and 'outsider'
      // internal group roles.
      if ($role_id == 'anonymous' || $role_id == 'authenticated') {
        continue;
      }

      // Build a list of group role definitions but do not save them yet so we
      // can check whether they already exist in bulk instead of trying to find
      // out on an individual basis here.
      foreach ($group_type_ids as $group_type_id) {
        $group_role_id = $this->groupRoleSynchronizer->getGroupRoleId($group_type_id, $role_id);
        $definitions[$group_role_id] = [
          'id' => $group_role_id,
          'label' => $user_roles[$role_id]->label(),
          'weight' => $user_roles[$role_id]->getWeight(),
          'internal' => TRUE,
          'audience' => 'outsider',
          'group_type' => $group_type_id,
          'permissions_ui' => FALSE,
          // Adding the user role as an enforced dependency will automatically
          // delete any synchronized group role when its corresponding user role
          // is deleted.
          'dependencies' => [
            'enforced' => [
              'config' => [$user_roles[$role_id]->getConfigDependencyName()],
            ],
          ],
        ];
      }
    }

    // See if the roles we just defined already exist.
    $query = $this->getQuery();
    $query->condition('id', array_keys($definitions));

    // Create the group roles that do not exist yet.
    foreach (array_diff_key($definitions, $query->execute()) as $definition) {
      $this->save($this->create($definition));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function updateSynchronizedLabels(RoleInterface $role) {
    $group_roles = $this->loadMultiple($this->groupRoleSynchronizer->getGroupRoleIdsByUserRole($role->id()));
    foreach ($group_roles as $group_role) {
      $group_role->set('label', $role->label());
      $this->save($group_role);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function resetUserGroupRoleCache(AccountInterface $account, GroupInterface $group = NULL) {
    $uid = $account->id();
    if (isset($group)) {
      unset($this->userGroupRoleIds[$uid][$group->id()]);
    }
    else {
      unset($this->userGroupRoleIds[$uid]);
    }
  }

}
