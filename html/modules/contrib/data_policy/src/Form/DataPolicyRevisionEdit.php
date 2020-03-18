<?php

namespace Drupal\data_policy\Form;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityManagerInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a form for editing a Data policy revision.
 *
 * @ingroup data_policy
 */
class DataPolicyRevisionEdit extends DataPolicyForm {

  /**
   * Constructs a DataPolicyRevisionEdit object.
   *
   * @param \Drupal\Core\Entity\EntityManagerInterface $entity_manager
   *   The entity manager.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entity_type_bundle_info
   *   The entity type bundle service.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   */
  public function __construct(EntityManagerInterface $entity_manager, EntityTypeBundleInfoInterface $entity_type_bundle_info = NULL, TimeInterface $time = NULL, ModuleHandlerInterface $module_handler = NULL, ConfigFactoryInterface $config_factory) {
    parent::__construct($entity_manager, $entity_type_bundle_info, $time);

    $this->moduleHandler = $module_handler;
    $this->configFactory = $config_factory;

    $entity_id = $this->config('data_policy.data_policy')->get('entity_id');

    $this->entity = $this->entityManager->getStorage('data_policy')
      ->load($entity_id);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity.manager'),
      $container->get('entity_type.bundle.info'),
      $container->get('datetime.time'),
      $container->get('module_handler'),
      $container->get('config.factory')
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
  public function buildForm(array $form, FormStateInterface $form_state, $data_policy_revision = NULL) {
    /** @var \Drupal\data_policy\Entity\DataPolicyInterface $entity */
    $entity = &$this->entity;

    $entity = $this->entityManager->getStorage('data_policy')
      ->loadRevision($data_policy_revision);

    $form = parent::buildForm($form, $form_state);

    $form['active_revision']['#default_value'] = $entity->isDefaultRevision();
    $form['active_revision']['#disabled'] = $entity->isDefaultRevision();
    $form['new_revision']['#default_value'] = FALSE;

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    /** @var \Drupal\data_policy\Entity\DataPolicyInterface $entity */
    $entity = &$this->entity;

    if (!empty($form_state->getValue('active_revision')) && !$entity->isDefaultRevision()) {
      $entity->isDefaultRevision(TRUE);
      $config = $this->configFactory->getEditable('data_policy.data_policy');
      $ids = $config->get('revision_ids');
      $ids[$entity->getRevisionId()] = TRUE;
      $config->set('revision_ids', $ids)->save();
    }

    $entity->save();

    $this->messenger()->addStatus($this->t('Saved revision.'));

    $form_state->setRedirect('entity.data_policy.version_history');
  }

  /**
   * {@inheritdoc}
   */
  public function clearMessage() {
    return FALSE;
  }

}
