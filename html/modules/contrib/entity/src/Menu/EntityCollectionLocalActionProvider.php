<?php

namespace Drupal\entity\Menu;

use Drupal\Core\Entity\EntityTypeInterface;

/**
 * Provides a action link to the add page or add form on the collection.
 */
class EntityCollectionLocalActionProvider implements EntityLocalActionProviderInterface {

  /**
   * {@inheritdoc}
   */
  public function buildLocalActions(EntityTypeInterface $entity_type) {
    $actions = [];
    if ($entity_type->hasLinkTemplate('collection')) {
      $entity_type_id = $entity_type->id();

      if ($entity_type->hasLinkTemplate('add-page')) {
        $route_name = "entity.$entity_type_id.add_page";
      }
      elseif ($entity_type->hasLinkTemplate('add-form')) {
        $route_name = "entity.$entity_type_id.add_form";
      }

      if (isset($route_name)) {
        $actions[$route_name] = [
          // The title is translated at runtime by EntityAddLocalAction.
          /* @see \Drupal\entity\Menu\EntityAddLocalAction::getTitle() */
          'title' => 'Add ' . $entity_type->getSingularLabel(),
          'route_name' => $route_name,
          'appears_on' => ["entity.$entity_type_id.collection"],
          'class' => EntityAddLocalAction::class,
        ];
      }
    }
    return $actions;
  }

}
