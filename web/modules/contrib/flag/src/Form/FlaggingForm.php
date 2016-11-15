<?php

namespace Drupal\flag\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Provides the flagging form for field entry.
 */
class FlaggingForm extends ContentEntityForm {

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function actions(array $form, FormStateInterface $form_state) {
    $actions = parent::actions($form, $form_state);

    if ($this->entity->isNew()) {
      $actions['submit']['#value'] = $this->t('Create Flagging');
    }
    else {
      $actions['submit']['#value'] = $this->t('Update Flagging');
    }

    // Customize the delete link.
    if (isset($actions['delete'])) {
      // @todo Why does the access call always fail?
      unset($actions['delete']['#access']);

      $actions['delete']['#title'] = $this->t('Delete Flagging');

      // Build the delete url from route. We need to build this manually
      // otherwise Drupal will try to build the flagging entity's delete-form
      // link. Since that route doesn't use the flagging ID, Drupal can't build
      // the link for us.
      $route_params = [
        'flag' => $this->entity->getFlagId(),
        'entity_id' => $this->entity->getFlaggableId(),
        'destination' => \Drupal::request()->get('destination'),
      ];
      $url = Url::fromRoute('flag.field_entry.delete', $route_params);

      $actions['delete']['#url'] = $url;
    }

    return $actions;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $entity = $this->entity;
    $entity->save();
  }
}
