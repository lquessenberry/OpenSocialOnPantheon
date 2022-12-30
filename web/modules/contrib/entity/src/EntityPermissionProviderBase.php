<?php

namespace Drupal\entity;

use Drupal\Core\Entity\EntityHandlerInterface;
use Drupal\Core\Entity\EntityPublishedInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\user\EntityOwnerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @internal
 */
class EntityPermissionProviderBase implements EntityPermissionProviderInterface, EntityHandlerInterface {

  use StringTranslationTrait;

  /**
   * The entity type bundle info.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface
   */
  protected $entityTypeBundleInfo;

  /**
   * Constructs a new EntityPermissionProvider object.
   *
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entity_type_bundle_info
   *   The entity type bundle info.
   */
  public function __construct(EntityTypeBundleInfoInterface $entity_type_bundle_info) {
    $this->entityTypeBundleInfo = $entity_type_bundle_info;
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    return new static(
      $container->get('entity_type.bundle.info')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildPermissions(EntityTypeInterface $entity_type) {
    $entity_type_id = $entity_type->id();
    $has_owner = $entity_type->entityClassImplements(EntityOwnerInterface::class);
    $plural_label = $entity_type->getPluralLabel();

    $permissions = [];

    $admin_permission = $entity_type->getAdminPermission() ?: "administer {$entity_type_id}";
    $permissions[$admin_permission] = [
      'title' => $this->t('Administer @type', ['@type' => $plural_label]),
      'restrict access' => TRUE,
    ];
    if ($entity_type->hasLinkTemplate('collection')) {
      $permissions["access {$entity_type_id} overview"] = [
        'title' => $this->t('Access the @type overview page', ['@type' => $plural_label]),
      ];
    }
    if ($has_owner && $entity_type->entityClassImplements(EntityPublishedInterface::class)) {
      $permissions["view own unpublished {$entity_type_id}"] = [
        'title' => $this->t('View own unpublished @type', [
          '@type' => $plural_label,
        ]),
      ];
    }

    // Generate the other permissions based on granularity.
    if ($entity_type->getPermissionGranularity() === 'entity_type') {
      $permissions += $this->buildEntityTypePermissions($entity_type);
    }
    else {
      $permissions += $this->buildBundlePermissions($entity_type);
    }

    return $this->processPermissions($permissions, $entity_type);
  }

  /**
   * Adds the provider and converts the titles to strings to allow sorting.
   *
   * @param array $permissions
   *   The array of permissions.
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type.
   *
   * @return array
   *   An array of processed permissions.
   */
  protected function processPermissions(array $permissions, EntityTypeInterface $entity_type) {
    foreach ($permissions as $name => $permission) {
      // Permissions are grouped by provider on admin/people/permissions.
      $permissions[$name]['provider'] = $entity_type->getProvider();
      // TranslatableMarkup objects don't sort properly.
      $permissions[$name]['title'] = (string) $permission['title'];
    }
    return $permissions;
  }

  /**
   * Builds permissions for the entity_type granularity.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type.
   *
   * @return array
   *   The permissions.
   */
  protected function buildEntityTypePermissions(EntityTypeInterface $entity_type) {
    $entity_type_id = $entity_type->id();
    $has_owner = $entity_type->entityClassImplements(EntityOwnerInterface::class);
    $has_duplicate_form = $entity_type->hasLinkTemplate('duplicate-form');
    $singular_label = $entity_type->getSingularLabel();
    $plural_label = $entity_type->getPluralLabel();

    $permissions = [];
    $permissions["create {$entity_type_id}"] = [
      'title' => $this->t('Create @type', [
        '@type' => $plural_label,
      ]),
    ];
    if ($has_owner) {
      $permissions["update any {$entity_type_id}"] = [
        'title' => $this->t('Update any @type', [
          '@type' => $singular_label,
        ]),
      ];
      $permissions["update own {$entity_type_id}"] = [
        'title' => $this->t('Update own @type', [
          '@type' => $plural_label,
        ]),
      ];
      if ($has_duplicate_form) {
        $permissions["duplicate any {$entity_type_id}"] = [
          'title' => $this->t('Duplicate any @type', [
            '@type' => $singular_label,
          ]),
        ];
        $permissions["duplicate own {$entity_type_id}"] = [
          'title' => $this->t('Duplicate own @type', [
            '@type' => $plural_label,
          ]),
        ];
      }
      $permissions["delete any {$entity_type_id}"] = [
        'title' => $this->t('Delete any @type', [
          '@type' => $singular_label,
        ]),
      ];
      $permissions["delete own {$entity_type_id}"] = [
        'title' => $this->t('Delete own @type', [
          '@type' => $plural_label,
        ]),
      ];
    }
    else {
      $permissions["update {$entity_type_id}"] = [
        'title' => $this->t('Update @type', [
          '@type' => $plural_label,
        ]),
      ];
      if ($has_duplicate_form) {
        $permissions["duplicate {$entity_type_id}"] = [
          'title' => $this->t('Duplicate @type', [
            '@type' => $plural_label,
          ]),
        ];
      }
      $permissions["delete {$entity_type_id}"] = [
        'title' => $this->t('Delete @type', [
          '@type' => $plural_label,
        ]),
      ];
    }

    return $permissions;
  }

  /**
   * Builds permissions for the bundle granularity.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type.
   *
   * @return array
   *   The permissions.
   */
  protected function buildBundlePermissions(EntityTypeInterface $entity_type) {
    $entity_type_id = $entity_type->id();
    $bundles = $this->entityTypeBundleInfo->getBundleInfo($entity_type_id);
    $has_owner = $entity_type->entityClassImplements(EntityOwnerInterface::class);
    $has_duplicate_form = $entity_type->hasLinkTemplate('duplicate-form');
    $singular_label = $entity_type->getSingularLabel();
    $plural_label = $entity_type->getPluralLabel();

    $permissions = [];
    foreach ($bundles as $bundle_name => $bundle_info) {
      $permissions["create {$bundle_name} {$entity_type_id}"] = [
        'title' => $this->t('@bundle: Create @type', [
          '@bundle' => $bundle_info['label'],
          '@type' => $plural_label,
        ]),
      ];

      if ($has_owner) {
        $permissions["update any {$bundle_name} {$entity_type_id}"] = [
          'title' => $this->t('@bundle: Update any @type', [
            '@bundle' => $bundle_info['label'],
            '@type' => $singular_label,
          ]),
        ];
        $permissions["update own {$bundle_name} {$entity_type_id}"] = [
          'title' => $this->t('@bundle: Update own @type', [
            '@bundle' => $bundle_info['label'],
            '@type' => $plural_label,
          ]),
        ];
        if ($has_duplicate_form) {
          $permissions["duplicate any {$bundle_name} {$entity_type_id}"] = [
            'title' => $this->t('@bundle: Duplicate any @type', [
              '@bundle' => $bundle_info['label'],
              '@type' => $singular_label,
            ]),
          ];
          $permissions["duplicate own {$bundle_name} {$entity_type_id}"] = [
            'title' => $this->t('@bundle: Duplicate own @type', [
              '@bundle' => $bundle_info['label'],
              '@type' => $plural_label,
            ]),
          ];
        }
        $permissions["delete any {$bundle_name} {$entity_type_id}"] = [
          'title' => $this->t('@bundle: Delete any @type', [
            '@bundle' => $bundle_info['label'],
            '@type' => $singular_label,
          ]),
        ];
        $permissions["delete own {$bundle_name} {$entity_type_id}"] = [
          'title' => $this->t('@bundle: Delete own @type', [
            '@bundle' => $bundle_info['label'],
            '@type' => $plural_label,
          ]),
        ];
      }
      else {
        $permissions["update {$bundle_name} {$entity_type_id}"] = [
          'title' => $this->t('@bundle: Update @type', [
            '@bundle' => $bundle_info['label'],
            '@type' => $plural_label,
          ]),
        ];
        if ($has_duplicate_form) {
          $permissions["duplicate {$bundle_name} {$entity_type_id}"] = [
            'title' => $this->t('@bundle: Duplicate @type', [
              '@bundle' => $bundle_info['label'],
              '@type' => $plural_label,
            ]),
          ];
        }
        $permissions["delete {$bundle_name} {$entity_type_id}"] = [
          'title' => $this->t('@bundle: Delete @type', [
            '@bundle' => $bundle_info['label'],
            '@type' => $plural_label,
          ]),
        ];
      }
    }

    return $permissions;
  }

}
