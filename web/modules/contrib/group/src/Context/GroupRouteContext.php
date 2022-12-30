<?php

namespace Drupal\group\Context;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\Context\Context;
use Drupal\Core\Plugin\Context\ContextProviderInterface;
use Drupal\Core\Plugin\Context\EntityContext;
use Drupal\Core\Plugin\Context\EntityContextDefinition;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;

/**
 * Sets the current group as a context on group routes.
 */
class GroupRouteContext implements ContextProviderInterface {

  use GroupRouteContextTrait;
  use StringTranslationTrait;

  /**
   * Constructs a new GroupRouteContext.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $current_route_match
   *   The current route match object.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\StringTranslation\TranslationInterface $string_translation
   *   The string translation service.
   */
  public function __construct(RouteMatchInterface $current_route_match, EntityTypeManagerInterface $entity_type_manager, TranslationInterface $string_translation) {
    $this->currentRouteMatch = $current_route_match;
    $this->entityTypeManager = $entity_type_manager;
    $this->stringTranslation = $string_translation;
  }

  /**
   * {@inheritdoc}
   */
  public function getRuntimeContexts(array $unqualified_context_ids) {
    // Create an optional context definition for group entities.
    $context_definition = EntityContextDefinition::fromEntityTypeId('group')
      ->setRequired(FALSE);

    // Cache this context per group on the route.
    $cacheability = new CacheableMetadata();
    $cacheability->setCacheContexts(['route.group']);

    // Create a context from the definition and retrieved or created group.
    $context = new Context($context_definition, $this->getGroupFromRoute());
    $context->addCacheableDependency($cacheability);

    return ['group' => $context];
  }

  /**
   * {@inheritdoc}
   */
  public function getAvailableContexts() {
    return ['group' => EntityContext::fromEntityTypeId('group', $this->t('Group from URL'))];
  }

}
