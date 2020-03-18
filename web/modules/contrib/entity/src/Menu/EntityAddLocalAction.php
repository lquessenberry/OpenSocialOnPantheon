<?php

namespace Drupal\entity\Menu;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Menu\LocalActionDefault;
use Drupal\Core\Routing\RouteProviderInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Provides a local action to add an entity.
 */
class EntityAddLocalAction extends LocalActionDefault {

  use StringTranslationTrait;

  /**
   * The entity type.
   *
   * @var \Drupal\Core\Entity\EntityTypeInterface
   */
  protected $entityType;

  /**
   * Constructs a EntityAddLocalAction object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Routing\RouteProviderInterface $route_provider
   *   The route provider to load routes by name.
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type.
   * @param \Drupal\Core\StringTranslation\TranslationInterface $string_translation
   *   The string translation service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, RouteProviderInterface $route_provider, EntityTypeInterface $entity_type, TranslationInterface $string_translation) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $route_provider);

    $this->entityType = $entity_type;
    $this->setStringTranslation($string_translation);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    /* @var \Drupal\Core\Entity\EntityTypeManagerInterface */
    $entity_type_manager = $container->get('entity_type.manager');
    // The plugin ID is of the form
    // "entity.entity_actions:entity.$entity_type_id.collection".
    // @see entity.links.action.yml
    // @see \Drupal\entity\Menu\EntityCollectionLocalActionProvider::buildLocalActions()
    list(, $derivate_id) = explode(':', $plugin_id);
    list(, $entity_type_id, ) = explode('.', $derivate_id);
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('router.route_provider'),
      $entity_type_manager->getDefinition($entity_type_id),
      $container->get('string_translation')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getTitle(Request $request = NULL) {
    return (string) $this->t('Add @entity', [
      '@entity' => (string) $this->entityType->getSingularLabel(),
    ]);
  }

}
