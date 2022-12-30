<?php

namespace Drupal\select2_publish\Element;

use Drupal\Core\Entity\EntityPublishedInterface;
use Drupal\Core\Security\TrustedCallbackInterface;

/**
 * Additional callbacks to render the status properties.
 */
class StatusProperties implements TrustedCallbackInterface {

  /**
   * {@inheritdoc}
   */
  public static function trustedCallbacks() {
    return ['preRender'];
  }

  /**
   * Attach status properties to the render element.
   *
   * @param array $element
   *   The select2 render element.
   *
   * @return mixed
   *   The select2 render element.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public static function preRender(array $element) {
    if ($element['#target_type']) {
      $entity_manager = \Drupal::entityTypeManager();
      $entity_definition = $entity_manager->getDefinition($element['#target_type']);

      if (!$entity_definition->entityClassImplements(EntityPublishedInterface::class)) {
        return $element;
      }

      $entity_storage = $entity_manager->getStorage($element['#target_type']);
      /** @var \Drupal\Core\Entity\EntityPublishedInterface $entity */
      foreach ($entity_storage->loadMultiple(array_keys($element['#options'])) as $id => $entity) {
        $element['#options_attributes'][$id]['data-published'] = $entity->isPublished() ? 'true' : 'false';
      }

      $default_status = 'true';
      if ($element['#autocreate']) {
        /** @var \Drupal\Core\Entity\EntityPublishedInterface $entity */
        $entity = $entity_storage->create([$entity_definition->getKey('bundle') => $element['#autocreate']['bundle']]);
        $default_status = $entity->isPublished() ? 'true' : 'false';
      }

      $element['#attached']['library'][] = 'select2_publish/select2.publish';
      $element['#attributes']['data-select2-publish-default'] = $default_status;
    }
    return $element;
  }

}
