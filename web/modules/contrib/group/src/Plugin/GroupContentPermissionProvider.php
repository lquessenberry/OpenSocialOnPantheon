<?php

namespace Drupal\group\Plugin;

use Drupal\Core\Entity\EntityPublishedInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\user\EntityOwnerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides group permissions for GroupContent entities.
 */
class GroupContentPermissionProvider extends GroupContentHandlerBase implements GroupContentPermissionProviderInterface {

  /**
   * The entity type the enabler is for.
   *
   * @var \Drupal\Core\Entity\EntityTypeInterface
   */
  protected $entityType;

  /**
   * Whether the target entity type implements the EntityOwnerInterface.
   *
   * @var bool
   */
  protected $implementsOwnerInterface;

  /**
   * Whether the target entity type implements the EntityPublishedInterface.
   *
   * @var bool
   */
  protected $implementsPublishedInterface;

  /**
   * Whether the plugin defines permissions for the target entity type.
   *
   * @var bool
   */
  protected $definesEntityPermissions;

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, $plugin_id, array $definition) {
    /** @var EntityTypeManagerInterface $entity_type_manager */
    $entity_type_manager = $container->get('entity_type.manager');
    $entity_type = $entity_type_manager->getDefinition($definition['entity_type_id']);

    /** @var static $instance */
    $instance = parent::createInstance($container, $plugin_id, $definition);
    $instance->entityType = $entity_type;
    $instance->implementsOwnerInterface = $entity_type->entityClassImplements(EntityOwnerInterface::class);
    $instance->implementsPublishedInterface = $entity_type->entityClassImplements(EntityPublishedInterface::class);
    $instance->definesEntityPermissions = !empty($definition['entity_access']);
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getAdminPermission() {
    return $this->definition['admin_permission'];
  }

  /**
   * {@inheritdoc}
   */
  public function getRelationViewPermission($scope = 'any') {
    // @todo Implement view own permission.
    if ($scope === 'any') {
      // Backwards compatible permission name for 'any' scope.
      return "view $this->pluginId content";
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function getRelationUpdatePermission($scope = 'any') {
    return "update $scope $this->pluginId content";
  }

  /**
   * {@inheritdoc}
   */
  public function getRelationDeletePermission($scope = 'any') {
    return "delete $scope $this->pluginId content";
  }

  /**
   * {@inheritdoc}
   */
  public function getRelationCreatePermission() {
    return "create $this->pluginId content";
  }

  /**
   * {@inheritdoc}
   */
  public function getEntityViewPermission($scope = 'any') {
    if ($this->definesEntityPermissions) {
      // @todo Implement view own permission.
      if ($scope === 'any') {
        // Backwards compatible permission name for 'any' scope.
        return "view $this->pluginId entity";
      }
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function getEntityViewUnpublishedPermission($scope = 'any') {
    if ($this->definesEntityPermissions) {
      if ($this->implementsPublishedInterface) {
        // @todo Implement view own unpublished permission and add it here by
        // checking for $this->implementsOwnerInterface.
        if ($scope === 'any') {
          return "view $scope unpublished $this->pluginId entity";
        }
      }
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function getEntityUpdatePermission($scope = 'any') {
    if ($this->definesEntityPermissions) {
      if ($this->implementsOwnerInterface || $scope === 'any') {
        return "update $scope $this->pluginId entity";
      }
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function getEntityDeletePermission($scope = 'any') {
    if ($this->definesEntityPermissions) {
      if ($this->implementsOwnerInterface || $scope === 'any') {
        return "delete $scope $this->pluginId entity";
      }
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function getEntityCreatePermission() {
    if ($this->definesEntityPermissions) {
      return "create $this->pluginId entity";
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function getPermission($operation, $target, $scope = 'any') {
    assert(in_array($target, ['relation', 'entity'], TRUE), '$target must be either "relation" or "entity"');
    assert(in_array($scope, ['any', 'own'], TRUE), '$target must be either "relation" or "entity"');

    if ($target === 'relation') {
      switch ($operation) {
        case 'view':
          return $this->getRelationViewPermission($scope);
        case 'update':
          return $this->getRelationUpdatePermission($scope);
        case 'delete':
          return $this->getRelationDeletePermission($scope);
        case 'create':
          return $this->getRelationCreatePermission();
      }
    }
    elseif ($target === 'entity') {
      switch ($operation) {
        case 'view':
          return $this->getEntityViewPermission($scope);
        case 'view unpublished':
          return $this->getEntityViewUnpublishedPermission($scope);
        case 'update':
          return $this->getEntityUpdatePermission($scope);
        case 'delete':
          return $this->getEntityDeletePermission($scope);
        case 'create':
          return $this->getEntityCreatePermission();
      }
    }

    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function buildPermissions() {
    $permissions = [];

    // Provide permissions for the relation (i.e.: The group content entity).
    $prefix = 'Relation:';
    if ($name = $this->getAdminPermission()) {
      $permissions[$name] = $this->buildPermission("$prefix Administer relations");
      $permissions[$name]['restrict access'] = TRUE;
    }

    if ($name = $this->getRelationViewPermission()) {
      $permissions[$name] = $this->buildPermission("$prefix View any entity relations");
    }
    if ($name = $this->getRelationViewPermission('own')) {
      $permissions[$name] = $this->buildPermission("$prefix View own entity relations");
    }
    if ($name = $this->getRelationUpdatePermission()) {
      $permissions[$name] = $this->buildPermission("$prefix Edit any entity relations");
    }
    if ($name = $this->getRelationUpdatePermission('own')) {
      $permissions[$name] = $this->buildPermission("$prefix Edit own entity relations");
    }
    if ($name = $this->getRelationDeletePermission()) {
      $permissions[$name] = $this->buildPermission("$prefix Delete any entity relations");
    }
    if ($name = $this->getRelationDeletePermission('own')) {
      $permissions[$name] = $this->buildPermission("$prefix Delete own entity relations");
    }

    if ($name = $this->getRelationCreatePermission()) {
      $permissions[$name] = $this->buildPermission(
        "$prefix Add entity relations",
        'Allows you to add an existing %entity_type entity to the group.'
      );
    }

    // Provide permissions for the actual entity being added to the group.
    $prefix = 'Entity:';
    if ($name = $this->getEntityViewPermission()) {
      $permissions[$name] = $this->buildPermission("$prefix View any %entity_type entities");
    }
    if ($name = $this->getEntityViewPermission('own')) {
      $permissions[$name] = $this->buildPermission("$prefix View own %entity_type entities");
    }
    if ($name = $this->getEntityViewUnpublishedPermission()) {
      $permissions[$name] = $this->buildPermission("$prefix View any unpublished %entity_type entities");
    }
    if ($name = $this->getEntityViewUnpublishedPermission('own')) {
      $permissions[$name] = $this->buildPermission("$prefix View own unpublished %entity_type entities");
    }
    if ($name = $this->getEntityUpdatePermission()) {
      $permissions[$name] = $this->buildPermission("$prefix Edit any %entity_type entities");
    }
    if ($name = $this->getEntityUpdatePermission('own')) {
      $permissions[$name] = $this->buildPermission("$prefix Edit own %entity_type entities");
    }
    if ($name = $this->getEntityDeletePermission()) {
      $permissions[$name] = $this->buildPermission("$prefix Delete any %entity_type entities");
    }
    if ($name = $this->getEntityDeletePermission('own')) {
      $permissions[$name] = $this->buildPermission("$prefix Delete own %entity_type entities");
    }

    if ($name = $this->getEntityCreatePermission()) {
      $permissions[$name] = $this->buildPermission(
        "$prefix Add %entity_type entities",
        'Allows you to create a new %entity_type entity and add it to the group.'
      );
    }

    return $permissions;
  }

  /**
   * Builds a permission with common translation arguments predefined.
   *
   * @param string $title
   *   The permission title.
   * @param string $description
   *   (optional) The permission description.
   *
   * @return array
   *   The permission with a default translatable markup replacement for both
   *   %plugin_name and %entity_type.
   */
  protected function buildPermission($title, $description = NULL) {
    $t_args = [
      '%plugin_name' => $this->definition['label'],
      '%entity_type' => $this->entityType->getSingularLabel(),
    ];

    $permission['title'] = $title;
    $permission['title_args'] = $t_args;

    if (isset($description)) {
      $permission['description'] = $description;
      $permission['description_args'] = $t_args;
    }

    return $permission;
  }

}
