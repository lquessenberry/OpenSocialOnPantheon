<?php

namespace Drupal\private_message\Entity\Builder;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityManagerInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityViewBuilder;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Theme\Registry;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Build handler for rpivate message threads.
 */
class PrivateMessageThreadViewBuilder extends EntityViewBuilder {

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * The configuration factory.
   *
   * @var \Drupal\Core\Config\Config
   */
  protected $config;

  /**
   * The class resolver service.
   *
   * @var \Drupal\Core\DependencyInjection\ClassResolverInterface
   */
  protected $classResolver;

  /**
   * The module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The form builder service.
   *
   * @var \Drupal\Core\Form\FormBuilderInterface
   */
  protected $formBuilder;

  /**
   * Constructs a PrivateMessageThreadViewBuilder object.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entityType
   *   The entity type.
   * @param \Drupal\Core\Entity\EntityManagerInterface $entityManager
   *   The entity manager service.
   * @param \Drupal\Core\Language\LanguageManagerInterface $languageManager
   *   The language manager service.
   * @param \Drupal\Core\Theme\Registry $themeRegistry
   *   The theme register.
   * @param \Drupal\Core\Session\AccountProxyInterface $currentUser
   *   The current user.
   */
  public function __construct(
    EntityTypeInterface $entityType,
    EntityManagerInterface $entityManager,
    LanguageManagerInterface $languageManager,
    Registry $themeRegistry,
    AccountProxyInterface $currentUser
  ) {
    parent::__construct($entityType, $entityManager, $languageManager, $themeRegistry);

    $this->currentUser = $currentUser;
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entityType) {
    return new static(
      $entityType,
      $container->get('entity.manager'),
      $container->get('language_manager'),
      $container->get('theme.registry'),
      $container->get('current_user')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function view(EntityInterface $entity, $view_mode = 'full', $langcode = NULL) {
    $build = parent::view($entity, $view_mode, $langcode);

    $classes = ['private-message-thread'];
    $classes[] = 'private-message-thread-' . $view_mode;

    $last_access_time = $entity->getLastAccessTimestamp($this->currentUser);
    $newest_message_timestamp = $entity->getNewestMessageCreationTimestamp();
    if ($last_access_time <= $newest_message_timestamp) {
      $classes[] = 'unread-thread';
    }

    if ($view_mode == 'inbox') {
      $url = Url::fromRoute('entity.private_message_thread.canonical', ['private_message_thread' => $entity->id()]);
      $build['inbox_link'] = [
        '#type' => 'link',
        '#url' => $url,
        '#title' => '',
        '#weight' => 9999,
        '#attributes' => ['data-thread-id' => $entity->id(), 'class' => ['private-message-inbox-thread-link']],
      ];
    }

    if ($view_mode == 'full') {
      $tags[] = 'private_message_thread:' . $entity->id() . ':view:uid:' . $this->currentUser->id();
      $tags[] = 'private_message_inbox_block:uid:' . $this->currentUser->id();
      $tags[] = 'private_message_notification_block:uid:' . $this->currentUser->id();

      Cache::invalidateTags($tags);

      $entity->updateLastAccessTime($this->currentUser);

      $build['#prefix'] = '<div id="private-message-page"><div id="private-message-thread-' . $entity->id() . '" class="' . implode(' ', $classes) . '" data-thread-id="' . $entity->id() . '" data-last-update="' . $entity->get('updated')->value . '">';
      $build['#suffix'] = '</div></div>';
    }
    else {
      $build['#prefix'] = '<div id="private-message-thread-' . $entity->id() . '" class="' . implode(' ', $classes) . '" data-thread-id="' . $entity->id() . '" data-last-update="' . $entity->get('updated')->value . '">';
      $build['#suffix'] = '</div>';
    }

    return $build;
  }

}
