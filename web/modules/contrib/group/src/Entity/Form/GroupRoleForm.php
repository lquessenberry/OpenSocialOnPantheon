<?php

namespace Drupal\group\Entity\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\group\Entity\GroupRole;

/**
 * Form controller for group role forms.
 */
class GroupRoleForm extends EntityForm {

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    /** @var \Drupal\group\Entity\GroupRoleInterface $group_role */
    $group_role = $this->entity;
    if ($group_role->isInternal()) {
      return [
        '#title' => $this->t('Error'),
        'description' => [
          '#prefix' => '<p>',
          '#suffix' => '</p>',
          '#markup' => $this->t('Cannot edit an internal group role directly.'),
        ],
      ];
    }

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);

    /** @var \Drupal\group\Entity\GroupRoleInterface $group_role */
    $group_role = $this->entity;
    $group_role_id = '';

    $form['label'] = [
      '#title' => $this->t('Name'),
      '#type' => 'textfield',
      '#default_value' => $group_role->label(),
      '#description' => $this->t('The human-readable name of this group role. This text will be displayed on the group permissions page.'),
      '#required' => TRUE,
      '#size' => 30,
    ];

    // Since group role IDs are prefixed by the group type's ID followed by a
    // period, we need to save some space for that.
    $subtract = strlen($group_role->getGroupTypeId()) + 1;

    // Since machine names with periods in it are technically not allowed, we
    // strip the group type ID prefix when editing a group role.
    if ($group_role->id()) {
      list(, $group_role_id) = explode('-', $group_role->id(), 2);
    }

    $form['id'] = [
      '#type' => 'machine_name',
      '#default_value' => $group_role_id,
      '#maxlength' => EntityTypeInterface::ID_MAX_LENGTH - $subtract,
      '#machine_name' => [
        'exists' => [$this, 'exists'],
        'source' => ['label'],
      ],
      '#description' => $this->t('A unique machine-readable name for this group role. It must only contain lowercase letters, numbers, and underscores.'),
      '#disabled' => !$group_role->isNew(),
      '#field_prefix' => $group_role->getGroupTypeId() . '-',
    ];

    $form['weight'] = [
      '#type' => 'value',
      '#value' => $group_role->getWeight(),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  protected function actions(array $form, FormStateInterface $form_state) {
    $actions = parent::actions($form, $form_state);
    $actions['submit']['#value'] = $this->t('Save group role');
    $actions['delete']['#value'] = $this->t('Delete group role');
    return $actions;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);

    $id = trim($form_state->getValue('id'));
    // '0' is invalid, since elsewhere we might check it using empty().
    if ($id == '0') {
      $form_state->setErrorByName('id', $this->t("Invalid machine-readable name. Enter a name other than %invalid.", ['%invalid' => $id]));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    /** @var \Drupal\group\Entity\GroupRoleInterface $group_role */
    $group_role = $this->entity;
    $group_role->set('id', $group_role->getGroupTypeId() . '-' . $group_role->id());
    $group_role->set('label', trim($group_role->label()));

    $status = $group_role->save();
    $t_args = ['%label' => $group_role->label()];

    if ($status == SAVED_UPDATED) {
      $this->messenger()->addStatus($this->t('The group role %label has been updated.', $t_args));
    }
    elseif ($status == SAVED_NEW) {
      $this->messenger()->addStatus($this->t('The group role %label has been added.', $t_args));

      $context = array_merge($t_args, ['link' => $group_role->toLink($this->t('View'), 'collection')->toString()]);
      $this->logger('group')->notice('Added group role %label.', $context);
    }

    $form_state->setRedirectUrl($group_role->toUrl('collection'));
  }

  /**
   * Checks whether a group role ID exists already.
   *
   * @param string $id
   *
   * @return bool
   *   Whether the ID is taken.
   */
  public function exists($id) {
    /** @var \Drupal\group\Entity\GroupRoleInterface $group_role */
    $group_role = $this->entity;
    return (boolean) GroupRole::load($group_role->getGroupTypeId() . '-' .$id);
  }

  /**
   * {@inheritdoc}
   */
  public function getEntityFromRouteMatch(RouteMatchInterface $route_match, $entity_type_id) {
    if ($route_match->getRawParameter($entity_type_id) !== NULL) {
      return $route_match->getParameter($entity_type_id);
    }

    // If we are on the create form, we can't extract an entity from the route,
    // so we need to create one based on the route parameters.
    $values = [];
    if ($route_match->getRawParameter('group_type') !== NULL) {
      $values['group_type'] = $route_match->getRawParameter('group_type');
    }
    return $this->entityTypeManager->getStorage($entity_type_id)->create($values);
  }

}
