<?php

namespace Drupal\like_and_dislike;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\votingapi\Entity\VoteType;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides dynamic permissions for nodes of different types.
 */
class LikeDislikePermissions implements ContainerInjectionInterface {

  use StringTranslationTrait;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The configuration factory.
   *
   * @var \Drupal\Core\Config\Config
   */
  protected $configFactory;

  /**
   * The bundle info service.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface
   */
  protected $bundleInfoService;

  /**
   * Constructs a \Drupal\like_and_dislike\Form\SettingsForm object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManager $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $bundle_info_service
   *   The bundle info service.
   */
  public function __construct(EntityTypeManager $entity_type_manager, ConfigFactoryInterface $config_factory, EntityTypeBundleInfoInterface $bundle_info_service) {
    $this->entityTypeManager = $entity_type_manager;
    $this->configFactory = $config_factory;
    $this->bundleInfoService = $bundle_info_service;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('config.factory'),
      $container->get('entity_type.bundle.info')
    );
  }

  /**
   * Builds a list of like_and_dislike related permissions.
   *
   * @return array
   *   An associative array of permission names and descriptions.
   */
  public function buildPermissions() {
    $permissions = [];
    $enabled_entity_types = $this->configFactory->get('like_and_dislike.settings')->get('enabled_types');
    $vote_types = VoteType::loadMultiple();

    foreach ($enabled_entity_types as $entity_type_id => $bundles) {
      // The entity type has no bundles. Add entity type permission only.
      $this->addLikeAndDislikePermission($permissions, $vote_types, $entity_type_id, $bundles);
    }
    return $permissions;
  }

  /**
   * Adds vote types permissions for given entity type and bundles.
   *
   * @param array &$permissions
   *   An array of created permissions.
   * @param \Drupal\votingapi\VoteTypeInterface[] $vote_types
   *   An array of voting types.
   * @param string $entity_type_id
   *   The entity type ID.
   * @param array $bundles
   *   An array of bundles. Empty in case entity has no bundles.
   */
  protected function addLikeAndDislikePermission(array &$permissions, $vote_types, $entity_type_id, array $bundles) {
    $entity_type = $this->entityTypeManager->getDefinition($entity_type_id, FALSE);

    // The entity type has no bundles other than the default one.
    if (empty($bundles)) {
      /** @var \Drupal\votingapi\VoteTypeInterface $vote_type */
      foreach ($vote_types as $vote_type) {
        $permissions["add or remove {$vote_type->id()} votes on $entity_type_id"] = [
          'title' => $this->t('%entity_type_name: add/remove %vote_type_name', [
            '%entity_type_name' => $entity_type->getLabel(),
            '%vote_type_name' => $vote_type->label(),
          ]),
        ];
      }
    }
    else {
      foreach ($bundles as $bundle) {
        $bundle_info = $this->bundleInfoService->getBundleInfo($entity_type_id)[$bundle];
        /** @var \Drupal\votingapi\VoteTypeInterface $vote_type */
        foreach ($vote_types as $vote_type) {
          $permissions["add or remove {$vote_type->id()} votes on $bundle of $entity_type_id"] = [
            'title' => $this->t('%entity_type (%bundle): add/remove %vote_type', [
              '%entity_type' => $entity_type->getLabel(),
              '%vote_type' => $vote_type->label(),
              '%bundle' => $bundle_info['label'],
            ]),
          ];
        }
      }
    }
  }

}
