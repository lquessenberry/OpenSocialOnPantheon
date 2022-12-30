<?php

namespace Drupal\data_policy\Form;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form handler for the InformBlock add and edit forms.
 */
class InformBlockForm extends EntityForm {

  /**
   * Constructs an InformBlockForm object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManager $entityTypeManager
   *   The entityTypeManager.
   */
  public function __construct(EntityTypeManager $entityTypeManager) {
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);

    $informblock = $this->entity;

    $form['status'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable on this page'),
      '#default_value' => $informblock->status ?? TRUE,
      '#disabled' => !$this->currentUser()->hasPermission('change inform and consent setting status'),
    ];

    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Title'),
      '#maxlength' => 255,
      '#default_value' => $informblock->label(),
      '#description' => $this->t('Indicate what will be explained on this page.'),
      '#required' => TRUE,
    ];

    $form['page'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Page'),
      '#maxlength' => 255,
      '#default_value' => $informblock->page,
      '#description' => $this->t('Specify page by using their path. An example path is %user-wildcard for every user edit page. %front is the front page.', [
        '%user-wildcard' => '/user/*/edit',
        '%front' => '<front>',
      ]),
      '#required' => TRUE,
    ];

    $form['id'] = [
      '#type' => 'machine_name',
      '#default_value' => $informblock->id(),
      '#machine_name' => [
        'exists' => [$this, 'exist'],
      ],
      '#disabled' => !$informblock->isNew(),
    ];

    $form['summary'] = [
      '#type' => 'text_format',
      '#title' => $this->t('Summary'),
      '#default_value' => $informblock->summary['value'] ?? '',
      '#format' => $informblock->summary['format'] ?? NULL,
      '#required' => TRUE,
      '#description' => $this->t('Summarise what data is collected.'),
    ];

    $form['body'] = [
      '#type' => 'text_format',
      '#title' => $this->t('Description'),
      '#default_value' => $informblock->body['value'] ?? '',
      '#format' => $informblock->body['format'] ?? NULL,
      '#required' => FALSE,
      '#description' => $this->t('Describe in detail what data is collected and how it is used.'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $informblock = $this->entity;
    $status = $informblock->save();

    if ($status) {
      $this->messenger()->addStatus($this->t('Saved the %label Example.', [
        '%label' => $informblock->label(),
      ]));
    }
    else {
      $this->messenger()->addStatus($this->t('The %label Example was not saved.', [
        '%label' => $informblock->label(),
      ]));
    }

    // Invalidate cache tags.
    $tags = ['config:block.block.datapolicyinform'];
    Cache::invalidateTags($tags);

    $form_state->setRedirect('entity.informblock.collection');
  }

  /**
   * Helper function to check whether an InformBlock entity exists.
   */
  public function exist($id) {
    $entity = $this->entityTypeManager->getStorage('informblock')->getQuery()
      ->condition('id', $id)
      ->execute();
    return (bool) $entity;
  }

}
