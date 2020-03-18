<?php

namespace Drupal\data_policy;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\Routing\AdminHtmlRouteProvider;
use Symfony\Component\Routing\Route;

/**
 * Provides routes for Data policy entities.
 *
 * @see \Drupal\Core\Entity\Routing\AdminHtmlRouteProvider
 * @see \Drupal\Core\Entity\Routing\DefaultHtmlRouteProvider
 */
class DataPolicyHtmlRouteProvider extends AdminHtmlRouteProvider {

  /**
   * {@inheritdoc}
   */
  public function getRoutes(EntityTypeInterface $entity_type) {
    $collection = parent::getRoutes($entity_type);

    $entity_type_id = $entity_type->id();

    if ($history_route = $this->getHistoryRoute($entity_type)) {
      $collection->add("entity.{$entity_type_id}.version_history", $history_route);
    }

    if ($revision_route = $this->getRevisionRoute($entity_type)) {
      $collection->add("entity.{$entity_type_id}.revision", $revision_route);
    }

    if ($edit_route = $this->getRevisionEditRoute($entity_type)) {
      $collection->add("entity.{$entity_type_id}.revision_edit", $edit_route);
    }

    if ($revert_route = $this->getRevisionRevertRoute($entity_type)) {
      $collection->add("entity.{$entity_type_id}.revision_revert", $revert_route);
    }

    if ($delete_route = $this->getRevisionDeleteRoute($entity_type)) {
      $collection->add("entity.{$entity_type_id}.revision_delete", $delete_route);
    }

    if ($translation_route = $this->getRevisionTranslationRevertRoute($entity_type)) {
      $collection->add("entity.{$entity_type_id}.translation_revert", $translation_route);
    }

    return $collection;
  }

  /**
   * Gets the version history route.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type.
   *
   * @return \Symfony\Component\Routing\Route|null
   *   The generated route, if available.
   */
  protected function getHistoryRoute(EntityTypeInterface $entity_type) {
    if (!$entity_type->hasLinkTemplate('version-history')) {
      return NULL;
    }

    $route = new Route($entity_type->getLinkTemplate('version-history'));

    $route
      ->setDefaults([
        '_title' => 'Data policy',
        '_controller' => '\Drupal\data_policy\Controller\DataPolicy::revisionsOverviewPage',
      ])
      ->setRequirement('_permission', 'view all data policy revisions')
      ->setOption('_admin_route', TRUE);

    return $route;
  }

  /**
   * Gets the revision route.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type.
   *
   * @return \Symfony\Component\Routing\Route|null
   *   The generated route, if available.
   */
  protected function getRevisionRoute(EntityTypeInterface $entity_type) {
    if ($entity_type->hasLinkTemplate('revision')) {
      $route = new Route($entity_type->getLinkTemplate('revision'));
      $route
        ->setDefaults([
          '_controller' => '\Drupal\data_policy\Controller\DataPolicy::revisionOverviewPage',
          '_title_callback' => '\Drupal\data_policy\Controller\DataPolicy::revisionOverviewTitle',
        ])
        ->setRequirement('_permission', 'access data policy revisions')
        ->setOption('_admin_route', TRUE);

      return $route;
    }
  }

  /**
   * Gets the revision edit route.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type.
   *
   * @return \Symfony\Component\Routing\Route|null
   *   The generated route, if available.
   */
  protected function getRevisionEditRoute(EntityTypeInterface $entity_type) {
    if (!$entity_type->hasLinkTemplate('revision_edit')) {
      return NULL;
    }

    $route = new Route($entity_type->getLinkTemplate('revision_edit'));

    $route
      ->setDefaults([
        '_form' => '\Drupal\data_policy\Form\DataPolicyRevisionEdit',
        '_title' => 'Edit revision',
      ])
      ->setRequirement('_custom_access', '\Drupal\data_policy\Controller\DataPolicy::revisionEditAccess')
      ->setOption('_admin_route', TRUE);

    return $route;
  }

  /**
   * Gets the revision revert route.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type.
   *
   * @return \Symfony\Component\Routing\Route|null
   *   The generated route, if available.
   */
  protected function getRevisionRevertRoute(EntityTypeInterface $entity_type) {
    if ($entity_type->hasLinkTemplate('revision_revert')) {
      $route = new Route($entity_type->getLinkTemplate('revision_revert'));
      $route
        ->setDefaults([
          '_form' => '\Drupal\data_policy\Form\DataPolicyRevisionRevertForm',
          '_title' => 'Revert to earlier revision',
        ])
        ->setRequirement('_permission', 'revert all data policy revisions')
        ->setOption('_admin_route', TRUE);

      return $route;
    }
  }

  /**
   * Gets the revision delete route.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type.
   *
   * @return \Symfony\Component\Routing\Route|null
   *   The generated route, if available.
   */
  protected function getRevisionDeleteRoute(EntityTypeInterface $entity_type) {
    if ($entity_type->hasLinkTemplate('revision_delete')) {
      $route = new Route($entity_type->getLinkTemplate('revision_delete'));
      $route
        ->setDefaults([
          '_form' => '\Drupal\data_policy\Form\DataPolicyRevisionDeleteForm',
          '_title' => 'Delete earlier revision',
        ])
        ->setRequirement('_permission', 'delete all data policy revisions')
        ->setOption('_admin_route', TRUE);

      return $route;
    }
  }

  /**
   * Gets the revision translation revert route.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type.
   *
   * @return \Symfony\Component\Routing\Route|null
   *   The generated route, if available.
   */
  protected function getRevisionTranslationRevertRoute(EntityTypeInterface $entity_type) {
    if ($entity_type->hasLinkTemplate('translation_revert')) {
      $route = new Route($entity_type->getLinkTemplate('translation_revert'));
      $route
        ->setDefaults([
          '_form' => '\Drupal\data_policy\Form\DataPolicyRevisionRevertTranslationForm',
          '_title' => 'Revert to earlier revision of a translation',
        ])
        ->setRequirement('_permission', 'revert all data policy revisions')
        ->setOption('_admin_route', TRUE);

      return $route;
    }
  }

}
