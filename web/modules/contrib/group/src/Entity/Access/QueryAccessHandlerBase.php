<?php

namespace Drupal\group\Entity\Access;

use Drupal\Core\Entity\EntityHandlerInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\entity\QueryAccess\QueryAccessEvent;
use Drupal\entity\QueryAccess\QueryAccessHandlerInterface;
use Drupal\group\Access\ChainGroupPermissionCalculatorInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Controls query access for the Group module's entities.
 *
 * @see \Drupal\entity\QueryAccess\QueryAccessHandler
 */
abstract class QueryAccessHandlerBase implements EntityHandlerInterface, QueryAccessHandlerInterface {

  /**
   * The entity type.
   *
   * @var \Drupal\Core\Entity\EntityTypeInterface
   */
  protected $entityType;

  /**
   * The event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected $eventDispatcher;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * The group permission calculator.
   *
   * @var \Drupal\group\Access\GroupPermissionCalculatorInterface
   */
  protected $groupPermissionCalculator;

  /**
   * Constructs a new QueryAccessHandlerBase object.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher
   *   The event dispatcher.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user.
   * @param \Drupal\group\Access\ChainGroupPermissionCalculatorInterface $permission_calculator
   *   The group permission calculator.
   */
  public function __construct(EntityTypeInterface $entity_type, EventDispatcherInterface $event_dispatcher, AccountInterface $current_user, ChainGroupPermissionCalculatorInterface $permission_calculator) {
    $this->entityType = $entity_type;
    $this->eventDispatcher = $event_dispatcher;
    $this->currentUser = $current_user;
    $this->groupPermissionCalculator = $permission_calculator;
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    return new static(
      $entity_type,
      $container->get('event_dispatcher'),
      $container->get('current_user'),
      $container->get('group_permission.chain_calculator')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getConditions($operation, AccountInterface $account = NULL) {
    $account = $account ?: $this->currentUser;
    $entity_type_id = $this->entityType->id();
    $conditions = $this->buildConditions($operation, $account);

    // Allow other modules to modify the conditions before they are used.
    $event = new QueryAccessEvent($conditions, $operation, $account, $entity_type_id);
    $this->eventDispatcher->dispatch("entity.query_access", $event);
    $this->eventDispatcher->dispatch("entity.query_access.{$entity_type_id}", $event);

    return $conditions;
  }

  /**
   * Builds the conditions for the given operation and account.
   *
   * @param string $operation
   *   The access operation. Usually one of "view", "update" or "delete".
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user for which to restrict access.
   *
   * @return \Drupal\entity\QueryAccess\ConditionGroup
   *   The conditions.
   */
  abstract protected function buildConditions($operation, AccountInterface $account);

}
