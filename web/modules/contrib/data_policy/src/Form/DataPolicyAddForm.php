<?php

namespace Drupal\data_policy\Form;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Form for adding data policy.
 *
 * @package Drupal\data_policy\Form
 */
class DataPolicyAddForm extends ContentEntityForm {

  /**
   * The current route match.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $routeMatch;

  /**
   * The currently active request object.
   *
   * @var \Symfony\Component\HttpFoundation\Request
   */
  protected $request;

  /**
   * The route name.
   *
   * @var string
   */
  private $routeName;

  /**
   * The current entity id from request.
   *
   * @var int
   */
  private $entityId;

  /**
   * DataPolicyAddForm constructor.
   *
   * @param \Drupal\Core\Entity\EntityRepositoryInterface $entity_repository
   *   The entity repository.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entity_type_bundle_info
   *   The entity type bundle info.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The current route match.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   */
  public function __construct(
    EntityRepositoryInterface $entity_repository,
    EntityTypeBundleInfoInterface $entity_type_bundle_info,
    TimeInterface $time,
    RouteMatchInterface $route_match,
    Request $request
  ) {
    parent::__construct($entity_repository, $entity_type_bundle_info, $time);
    $this->routeMatch = $route_match;
    $this->request = $request;
    $this->routeName = $this->routeMatch->getRouteName();
    $this->entityId = $this->request->get('entity_id');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity.repository'),
      $container->get('entity_type.bundle.info'),
      $container->get('datetime.time'),
      $container->get('current_route_match'),
      $container->get('request_stack')->getCurrentRequest()
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    if ($this->routeName === 'entity.data_policy.revision_add') {
      $this->entity = $this->entityTypeManager->getStorage('data_policy')->load($this->entityId);

      $form = parent::buildForm($form, $form_state);

      $form['active_revision']['#default_value'] = TRUE;
      $form['active_revision']['#disabled'] = TRUE;
      $form['new_revision']['#default_value'] = FALSE;

      return $form;
    }

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);
    $this->messenger()->addStatus($this->t('Successfully created a new "%name" entity.', ['%name' => $this->entity->label()]));
    $form_state->setRedirect('entity.data_policy.collection');

    return $this->entity;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    if ($this->routeName === 'entity.data_policy.revision_add') {
      $this->entity->setNewRevision(TRUE);
      $active_revision = !empty($form_state->getValue('active_revision'));

      if (!$active_revision) {
        $this->entity->isDefaultRevision(FALSE);
      }

      $this->entity->set('name', $form_state->getValue('name'));
      $this->entity->set('field_description', $form_state->getValue('field_description'));
      $this->entity->set('revision_log_message', $form_state->getValue('revision_log_message'));
      $this->entity->setRevisionCreationTime($this->time->getRequestTime());
      $this->entity->setRevisionUserId($this->currentUser()->id());

      $this->entity->save();
      $this->messenger()->addStatus($this->t('Created new revision.'));

      $form_state->setRedirect('entity.data_policy.version_history', ['entity_id' => $this->entityId]);
    }
    else {
      return parent::save($form, $form_state);
    }

  }

}
