<?php

namespace Drupal\data_policy\Form;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Provides a form for editing a Data policy revision.
 *
 * @ingroup data_policy
 */
class DataPolicyRevisionEdit extends DataPolicyForm {

  /**
   * The currently active request object.
   *
   * @var \Symfony\Component\HttpFoundation\Request
   */
  protected $request;

  /**
   * The current revision id from request.
   *
   * @var int
   */
  private $revisionId;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a DataPolicyRevisionEdit object.
   *
   * @param \Drupal\Core\Entity\EntityRepositoryInterface $entity_repository
   *   The entity manager.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entity_type_bundle_info
   *   The entity type bundle service.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(
    EntityRepositoryInterface $entity_repository,
    EntityTypeBundleInfoInterface $entity_type_bundle_info,
    TimeInterface $time,
    ModuleHandlerInterface $module_handler,
    ConfigFactoryInterface $config_factory,
    Request $request,
    EntityTypeManagerInterface $entity_type_manager
  ) {
    parent::__construct($entity_repository, $entity_type_bundle_info, $time);
    $this->moduleHandler = $module_handler;
    $this->configFactory = $config_factory;
    $this->request = $request;
    $this->revisionId = $this->request->get('data_policy_revision');
    $this->entityTypeManager = $entity_type_manager;

    $this->entity = $this->entityTypeManager->getStorage('data_policy');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity.repository'),
      $container->get('entity_type.bundle.info'),
      $container->get('datetime.time'),
      $container->get('module_handler'),
      $container->get('config.factory'),
      $container->get('request_stack')->getCurrentRequest(),
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'data_policy_data_policy_revision_edit';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $this->entity = $this->entityTypeManager->getStorage('data_policy')->loadRevision($this->revisionId);
    $form = parent::buildForm($form, $form_state);

    $form['active_revision']['#default_value'] = $this->entity->isDefaultRevision();
    $form['active_revision']['#disabled'] = $this->entity->isDefaultRevision();
    $form['new_revision']['#default_value'] = FALSE;

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    if (!empty($form_state->getValue('active_revision')) && !$this->entity->isDefaultRevision()) {
      $this->entity->isDefaultRevision(TRUE);
      $config = $this->configFactory->getEditable('data_policy.data_policy');
      $ids = $config->get('revision_ids');
      $ids[$this->entity->getRevisionId()] = TRUE;
      $config->set('revision_ids', $ids)->save();
    }

    $this->entity->save();
    $this->messenger()->addStatus($this->t('Saved revision.'));
    $form_state->setRedirect('entity.data_policy.version_history', ['entity_id' => $this->entity->id()]);
  }

  /**
   * {@inheritdoc}
   */
  public function clearMessage() {
    return FALSE;
  }

}
