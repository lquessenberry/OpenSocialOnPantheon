<?php

namespace Drupal\group\Entity\Controller;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Routing\RedirectDestinationInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Routing\RouterInterface;

/**
 * Provides a list controller for group entities.
 *
 * @ingroup group
 */
class GroupListBuilder extends EntityListBuilder {

  /**
   * The redirect destination service.
   *
   * @var \Drupal\Core\Routing\RedirectDestinationInterface
   */
  protected $redirectDestination;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The router.
   *
   * @var \Symfony\Component\Routing\RouterInterface
   */
  protected $router;

  /**
   * Constructs a new GroupListBuilder object.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type definition.
   * @param \Drupal\Core\Entity\EntityStorageInterface $storage
   *   The entity storage class.
   * @param \Drupal\Core\Routing\RedirectDestinationInterface $redirect_destination
   *   The redirect destination service.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   * @param \Symfony\Component\Routing\RouterInterface $router
   *   The router.
   */
  public function __construct(EntityTypeInterface $entity_type, EntityStorageInterface $storage, RedirectDestinationInterface $redirect_destination, AccountInterface $current_user, ModuleHandlerInterface $module_handler, RouterInterface $router) {
    parent::__construct($entity_type, $storage);
    $this->redirectDestination = $redirect_destination;
    $this->currentUser = $current_user;
    $this->moduleHandler = $module_handler;
    $this->router = $router;
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    return new static(
      $entity_type,
      $container->get('entity_type.manager')->getStorage($entity_type->id()),
      $container->get('redirect.destination'),
      $container->get('current_user'),
      $container->get('module_handler'),
      $container->get('router.no_access_checks')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header = [
      'gid' => [
        'data' => $this->t('Group ID'),
        'specifier' => 'id',
        'field' => 'id',
      ],
      'label' => [
        'data' => $this->t('Name'),
        'specifier' => 'label',
        'field' => 'label',
      ],
      'type' => [
        'data' => $this->t('Type'),
        'specifier' =>'type',
        'field' => 'type',
      ],
      'status' => [
        'data' => $this->t('Status'),
        'specifier' =>'status',
        'field' => 'status',
      ],
      'uid' => [
        'data' => $this->t('Owner'),
      ],
    ];
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /** @var \Drupal\group\Entity\GroupInterface $entity */
    $row['id'] = $entity->id();
    // EntityListBuilder sets the table rows using the #rows property, so we
    // need to add the render array using the 'data' key.
    $row['name']['data'] = $entity->toLink()->toRenderable();
    $row['type'] = $entity->getGroupType()->label();
    $row['status'] = $entity->isPublished() ? $this->t('Published') : $this->t('Unpublished');
    $row['uid'] = $entity->getOwner()->label();
    return $row + parent::buildRow($entity);
  }

  /**
   * {@inheritdoc}
   */
  public function render() {
    $build = parent::render();
    $build['table']['#empty'] = $this->t('There are no groups yet.');
    return $build;
  }

  /**
   * {@inheritdoc}
   */
  protected function getEntityIds() {
    $query = $this->getStorage()->getQuery();

    // Add a simple table sort by header, see ::buildHeader().
    $header = $this->buildHeader();
    $query->tableSort($header);

    // Only add the pager if a limit is specified.
    if ($this->limit) {
      $query->pager($this->limit);
    }

    return $query->execute();
  }

  /**
   * {@inheritdoc}
   */
  protected function getDefaultOperations(EntityInterface $entity) {
    $operations = parent::getDefaultOperations($entity);

    /** @var \Drupal\group\Entity\GroupInterface $entity */
    if ($this->moduleHandler->moduleExists('views') && $entity->hasPermission('administer members', $this->currentUser)) {
      if ($this->router->getRouteCollection()->get('view.group_members.page_1') !== NULL) {
        $operations['members'] = [
          'title' => $this->t('Members'),
          'weight' => 15,
          'url' => Url::fromRoute('view.group_members.page_1', ['group' => $entity->id()]),
        ];
      }
    }

    if ($entity->getGroupType()->shouldCreateNewRevision() && $entity->hasPermission('view group revisions', $this->currentUser)) {
      $operations['revisions'] = [
        'title' => $this->t('Revisions'),
        'weight' => 20,
        'url' => $entity->toUrl('version-history'),
      ];
    }

    // Add the current path or destination as a redirect to the operation links.
    $destination = $this->redirectDestination->getAsArray();
    foreach ($operations as $key => $operation) {
      $operations[$key]['query'] = $destination;
    }

    return $operations;
  }

}
