<?php

namespace Drupal\graphql\Plugin\GraphQL\DataProducer\Field;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Field\EntityReferenceFieldItemListInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\graphql\GraphQL\Buffers\EntityBuffer;
use Drupal\graphql\GraphQL\Execution\FieldContext;
use Drupal\graphql\Plugin\GraphQL\DataProducer\DataProducerPluginBase;
use GraphQL\Deferred;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Loads entities from an entity reference field.
 *
 * @DataProducer(
 *   id = "entity_reference",
 *   name = @Translation("Entity reference"),
 *   description = @Translation("Loads entities from an entity reference field."),
 *   produces = @ContextDefinition("entity",
 *     label = @Translation("Entity"),
 *     multiple = TRUE
 *   ),
 *   consumes = {
 *     "entity" = @ContextDefinition("entity",
 *       label = @Translation("Parent entity")
 *     ),
 *     "field" = @ContextDefinition("string",
 *       label = @Translation("Field name")
 *     ),
 *     "language" = @ContextDefinition("string",
 *       label = @Translation("Entity language"),
 *       required = FALSE
 *     ),
 *     "bundle" = @ContextDefinition("string",
 *       label = @Translation("Entity bundle(s)"),
 *       multiple = TRUE,
 *       required = FALSE
 *     ),
 *     "access" = @ContextDefinition("boolean",
 *       label = @Translation("Check access"),
 *       required = FALSE,
 *       default_value = TRUE
 *     ),
 *     "access_user" = @ContextDefinition("entity:user",
 *       label = @Translation("User"),
 *       required = FALSE,
 *       default_value = NULL
 *     ),
 *     "access_operation" = @ContextDefinition("string",
 *       label = @Translation("Operation"),
 *       required = FALSE,
 *       default_value = "view"
 *     )
 *   }
 * )
 */
class EntityReference extends DataProducerPluginBase implements ContainerFactoryPluginInterface {

  use EntityReferenceTrait;

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The entity repository service.
   *
   * @var \Drupal\Core\Entity\EntityRepositoryInterface
   */
  protected $entityRepository;

  /**
   * The entity buffer service.
   *
   * @var \Drupal\graphql\GraphQL\Buffers\EntityBuffer
   */
  protected $entityBuffer;

  /**
   * {@inheritdoc}
   *
   * @codeCoverageIgnore
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('entity.repository'),
      $container->get('graphql.buffer.entity')
    );
  }

  /**
   * Constructor.
   *
   * @param array $configuration
   *   The plugin configuration array.
   * @param string $pluginId
   *   The plugin id.
   * @param array $pluginDefinition
   *   The plugin definition array.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager service.
   * @param \Drupal\Core\Entity\EntityRepositoryInterface $entityRepository
   *   The entity repository service.
   * @param \Drupal\graphql\GraphQL\Buffers\EntityBuffer $entityBuffer
   *   The entity buffer service.
   *
   * @codeCoverageIgnore
   */
  public function __construct(
    array $configuration,
    $pluginId,
    array $pluginDefinition,
    EntityTypeManagerInterface $entityTypeManager,
    EntityRepositoryInterface $entityRepository,
    EntityBuffer $entityBuffer
  ) {
    parent::__construct($configuration, $pluginId, $pluginDefinition);
    $this->entityTypeManager = $entityTypeManager;
    $this->entityRepository = $entityRepository;
    $this->entityBuffer = $entityBuffer;
  }

  /**
   * Resolve entity references in the given field name.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   * @param string $field
   * @param string|null $language
   * @param array|null $bundles
   * @param bool|null $access
   * @param \Drupal\Core\Session\AccountInterface|null $accessUser
   * @param string|null $accessOperation
   * @param \Drupal\graphql\GraphQL\Execution\FieldContext $context
   *
   * @return \GraphQL\Deferred|array
   *   A promise that will return referenced entities or empty array if there
   *   aren't any.
   */
  public function resolve(EntityInterface $entity, $field, ?string $language, ?array $bundles, ?bool $access, ?AccountInterface $accessUser, ?string $accessOperation, FieldContext $context) {
    if (!$entity instanceof FieldableEntityInterface || !$entity->hasField($field)) {
      return [];
    }

    $definition = $entity->getFieldDefinition($field);
    $type = $definition->getSetting('target_type');
    $values = $entity->get($field);
    if ($values instanceof EntityReferenceFieldItemListInterface) {
      $ids = array_map(function ($value) {
        return $value['target_id'];
      }, $values->getValue());

      $resolver = $this->entityBuffer->add($type, $ids);
      return new Deferred(function () use ($type, $language, $bundles, $access, $accessUser, $accessOperation, $resolver, $context) {
        return $this->getReferencedEntities($type, $language, $bundles, $access, $accessUser, $accessOperation, $resolver, $context);
      });
    }

    return [];
  }

}
