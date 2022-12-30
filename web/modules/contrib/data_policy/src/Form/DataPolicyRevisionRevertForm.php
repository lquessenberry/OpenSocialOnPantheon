<?php

namespace Drupal\data_policy\Form;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\data_policy\Entity\DataPolicyInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a form for reverting a Data policy revision.
 *
 * @ingroup data_policy
 */
class DataPolicyRevisionRevertForm extends ConfirmFormBase {

  /**
   * The Data policy revision.
   *
   * @var \Drupal\data_policy\Entity\DataPolicyInterface
   */
  protected $revision;

  /**
   * The Data policy storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $dataPolicyStorage;

  /**
   * The date formatter service.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * The time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * Constructs a new DataPolicyRevisionRevertForm.
   *
   * @param \Drupal\Core\Entity\EntityStorageInterface $entity_storage
   *   The Data policy storage.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $date_formatter
   *   The date formatter service.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   */
  public function __construct(EntityStorageInterface $entity_storage, DateFormatterInterface $date_formatter, TimeInterface $time) {
    $this->dataPolicyStorage = $entity_storage;
    $this->dateFormatter = $date_formatter;
    $this->time = $time;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager')->getStorage('data_policy'),
      $container->get('date.formatter'),
      $container->get('datetime.time')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'data_policy_data_policy_revision_revert_confirm';
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Are you sure you want to revert to the revision from %revision-date?', [
      '%revision-date' => $this->dateFormatter->format($this->revision->getRevisionCreationTime()),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return new Url('entity.data_policy.version_history', ['entity_id' => $this->revision->id()]);
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return $this->t('Revert');
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return $this->t('After making this revision active users will be asked again to agree with this revision.');
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $data_policy_revision = NULL) {
    $this->revision = $this->dataPolicyStorage->loadRevision($data_policy_revision);
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // The revision timestamp will be updated when the revision is saved. Keep
    // the original one for the confirmation message.
    $original_revision_timestamp = $this->revision->getRevisionCreationTime();

    $this->revision = $this->prepareRevertedRevision($this->revision, $form_state);

    $this->revision->revision_log = $this->t('Copy of the revision from %date.', [
      '%date' => $this->dateFormatter->format($original_revision_timestamp),
    ]);

    $this->revision->save();

    $this->logger('content')->notice('Data policy: reverted revision %revision.', [
      '%revision' => $this->revision->getRevisionId(),
    ]);

    $this->messenger()->addStatus($this->t('Data policy has been reverted to the revision from %revision-date.', [
      '%revision-date' => $this->dateFormatter->format($original_revision_timestamp),
    ]));

    $form_state->setRedirectUrl($this->getCancelUrl());
  }

  /**
   * Prepares a revision to be reverted.
   *
   * @param \Drupal\data_policy\Entity\DataPolicyInterface $revision
   *   The revision to be reverted.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return \Drupal\data_policy\Entity\DataPolicyInterface
   *   The prepared revision ready to be stored.
   */
  protected function prepareRevertedRevision(DataPolicyInterface $revision, FormStateInterface $form_state) {
    $revision->setNewRevision();
    $revision->isDefaultRevision(FALSE);
    $revision->setRevisionCreationTime($this->time->getRequestTime());

    return $revision;
  }

}
