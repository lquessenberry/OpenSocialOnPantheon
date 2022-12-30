<?php

namespace Drupal\graphql\Plugin\GraphQL\DataProducer\Entity;

use Drupal\Core\Entity\EntityInterface;
use Drupal\graphql\Plugin\GraphQL\DataProducer\DataProducerPluginBase;

/**
 * Returns the created time of an entity if it supports it.
 *
 * @DataProducer(
 *   id = "entity_created",
 *   name = @Translation("Entity created date"),
 *   description = @Translation("Returns the entity created date."),
 *   produces = @ContextDefinition("string",
 *     label = @Translation("Creation date"),
 *     required = FALSE
 *   ),
 *   consumes = {
 *     "entity" = @ContextDefinition("entity",
 *       label = @Translation("Entity")
 *     ),
 *     "format" = @ContextDefinition("string",
 *       label = @Translation("Date format"),
 *       required = FALSE
 *     )
 *   }
 * )
 */
class EntityCreated extends DataProducerPluginBase {

  /**
   * Resolver.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   * @param string|null $format
   *
   * @return string|null
   */
  public function resolve(EntityInterface $entity, $format = NULL) {
    // `getCreatedTime` is on NodeInterface which feels weird, since there
    // is a generic `EntityInterface`. Checking for method existence for now.
    if (method_exists($entity, 'getCreatedTime')) {
      $datetime = new \DateTime();
      $datetime->setTimestamp($entity->getCreatedTime());
      return $datetime->format($format ?? \DateTime::ISO8601);
    }

    return NULL;
  }

}
