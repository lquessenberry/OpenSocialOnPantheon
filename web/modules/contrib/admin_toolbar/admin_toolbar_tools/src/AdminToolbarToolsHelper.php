<?php

namespace Drupal\admin_toolbar_tools;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Menu\LocalTaskManager;
use Drupal\Core\Render\Element;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Security\TrustedCallbackInterface;

/**
 * Admin Toolbar Tools helper service.
 */
class AdminToolbarToolsHelper implements TrustedCallbackInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The local task manger.
   *
   * @var \Drupal\Core\Menu\LocalTaskManager
   *   The local task manager menu.
   */
  protected $localTaskManager;

  /**
   * The route match interface.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   *   The route match.
   */
  protected $routeMatch;

  /**
   * Create an AdminToolbarToolsHelper object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Menu\LocalTaskManager $local_task_manager
   *   The local task manager.
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The route match.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, LocalTaskManager $local_task_manager, RouteMatchInterface $route_match) {
    $this->entityTypeManager = $entity_type_manager;
    $this->localTaskManager = $local_task_manager;
    $this->routeMatch = $route_match;
  }

  /**
   * {@inheritdoc}
   */
  public static function trustedCallbacks() {
    return ['localTasksTrayLazyBuilder'];
  }

  /**
   * Lazy builder callback for the admin_toolbar_local_tasks tray items.
   *
   * @return array
   *   A renderable array as expected by the renderer service.
   */
  public function localTasksTrayLazyBuilder() {
    // Get primary local task links and inject them into new
    // admin_toolbar_local_tasks toolbar tray.
    $links = $this->localTaskManager->getLocalTasks($this->routeMatch->getRouteName(), 0);
    if (!empty($links['tabs'])) {
      $build = [
        '#theme' => 'links',
        '#links' => [],
        '#attributes' => [
          'class' => ['toolbar-menu'],
        ],
      ];
      Element::children($links['tabs'], TRUE);
      $routes = Element::getVisibleChildren($links['tabs']);
      foreach ($routes as $route) {
        $build['#links'][$route] = $links['tabs'][$route]['#link'];
      }
      $links['cacheability']->applyTo($build);
      return $build;
    }

    return [];
  }

  /**
   * Gets a list of content entities.
   *
   * @return array
   *   An array of metadata about content entities.
   */
  public function getBundleableEntitiesList() {
    $entity_types = $this->entityTypeManager->getDefinitions();
    $content_entities = [];
    foreach ($entity_types as $key => $entity_type) {
      if ($entity_type->getBundleEntityType() && ($entity_type->get('field_ui_base_route') != '')) {
        $content_entities[$key] = [
          'content_entity' => $key,
          'content_entity_bundle' => $entity_type->getBundleEntityType(),
        ];
      }
    }
    return $content_entities;
  }

  /**
   * Gets an array of entity types that should trigger a menu rebuild.
   *
   * @return array
   *   An array of entity machine names.
   */
  public function getRebuildEntityTypes() {
    $types = ['menu'];
    $content_entities = $this->getBundleableEntitiesList();
    $types = array_merge($types, array_column($content_entities, 'content_entity_bundle'));
    return $types;
  }

}
