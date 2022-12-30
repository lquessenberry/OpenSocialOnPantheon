<?php

namespace Drupal\private_message\Entity\Builder;

use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityViewBuilder;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Theme\Registry;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Build handler for rpivate messages.
 */
class PrivateMessageViewBuilder extends EntityViewBuilder {

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * Constructs a PrivateMessageViewBuilder object.
   *
   * @param \Drupal\Core\Entity\EntityRepositoryInterface $entityRepository
   *   The entity repository service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity manager service.
   * @param \Drupal\Core\Language\LanguageManagerInterface $languageManager
   *   The language manager.
   * @param \Drupal\Core\Session\AccountProxyInterface $currentUser
   *   The current user.
   * @param \Drupal\Core\Theme\Registry $themeRegistry
   *   The theme registry.
   * @param \Drupal\Core\Entity\EntityDisplayRepositoryInterface $entity_display_repository
   *   The entity display repository.
   */
  public function __construct(
    EntityTypeInterface $entityType,
    EntityRepositoryInterface $entityRepository,
    LanguageManagerInterface $languageManager,
    AccountProxyInterface $currentUser,
    Registry $themeRegistry = NULL,
    EntityDisplayRepositoryInterface $entity_display_repository
  ) {
    parent::__construct($entityType, $entityRepository, $languageManager, $themeRegistry, $entity_display_repository);

    $this->currentUser = $currentUser;
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    return new static(
      $entity_type,
      $container->get('entity.repository'),
      $container->get('language_manager'),
      $container->get('current_user'),
      $container->get('theme.registry'),
      $container->get('entity_display.repository')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function view(EntityInterface $entity, $viewMode = 'default', $langcode = NULL) {
    $message = parent::view($entity, $viewMode, $langcode);

    $classes = ['private-message'];
    $classes[] = 'private-message-' . $viewMode;
    $classes[] = 'private-message-author-' . ($this->currentUser->id() == $entity->getOwnerId() ? 'self' : 'other');
    $id = 'private-message-' . $entity->id();

    $build['wrapper'] = [
      '#type' => 'container',
      '#attributes' => [
        'id' => $id,
        'data-message-id' => $entity->id(),
        'class' => $classes,
      ],
    ];
    $build['wrapper']['message'] = $message;

    $this->moduleHandler()->alter('private_message_view', $build, $entity, $viewMode);

    return $build;
  }

}
