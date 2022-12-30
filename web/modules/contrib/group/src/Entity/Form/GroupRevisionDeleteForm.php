<?php

namespace Drupal\group\Entity\Form;

use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\ContentEntityStorageInterface;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\group\Entity\GroupInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a form for deleting a group revision.
 */
class GroupRevisionDeleteForm extends ConfirmFormBase {

  /**
   * The group revision.
   *
   * @var \Drupal\group\Entity\GroupInterface
   */
  protected $revision;

  /**
   * The group storage.
   *
   * @var \Drupal\Core\Entity\ContentEntityStorageInterface
   */
  protected $groupStorage;

  /**
   * The date formatter service.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * Constructs a new GroupRevisionDeleteForm.
   *
   * @param \Drupal\Core\Entity\ContentEntityStorageInterface
   *   The group storage.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $date_formatter
   *   The date formatter service.
   */
  public function __construct(ContentEntityStorageInterface $group_storage, DateFormatterInterface $date_formatter) {
    $this->groupStorage = $group_storage;
    $this->dateFormatter = $date_formatter;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $entity_manager = $container->get('entity_type.manager');
    return new static(
      $entity_manager->getStorage('group'),
      $container->get('date.formatter')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'group_revision_delete_confirm';
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Are you sure you want to delete the revision from %revision-date?', ['%revision-date' => $this->dateFormatter->format($this->revision->getRevisionCreationTime())]);
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return $this->revision->toUrl('version-history');
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return $this->t('Delete');
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $group_revision = NULL) {
    $this->revision = $group_revision;
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->groupStorage->deleteRevision($this->revision->getRevisionId());

    $group_type = $this->revision->getGroupType();
    $this->logger('content')->notice('@type: deleted %title revision %revision.', [
      '@type' => $group_type->label(),
      '%title' => $this->revision->label(),
      '%revision' => $this->revision->getRevisionId(),
    ]);

    $this->messenger()->addStatus(
      $this->t('Revision from %revision-date of @type %title has been deleted.', [
        '%revision-date' => $this->dateFormatter->format($this->revision->getRevisionCreationTime()),
        '@type' => $group_type,
        '%title' => $this->revision->label(),
      ])
    );

    $redirect_route = 'entity.group.canonical';
    if ($this->countRevisions($this->revision) > 1) {
      $redirect_route = 'entity.group.version_history';
    }

    $form_state->setRedirect($redirect_route, ['group' => $this->revision->id()]);
  }

  /**
   * Counts the number of revisions.
   *
   * @param \Drupal\group\Entity\GroupInterface $group
   *   The group.
   *
   * @return int
   *   The number of revisions.
   */
  protected function countRevisions(GroupInterface $group) {
    return $this->groupStorage
      ->getQuery()
      ->allRevisions()
      ->condition('id', $group->id())
      ->count()
      ->execute();
  }

}
