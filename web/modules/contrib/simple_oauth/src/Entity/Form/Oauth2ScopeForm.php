<?php

namespace Drupal\simple_oauth\Entity\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\simple_oauth\Oauth2ScopeInterface;
use Drupal\simple_oauth\Plugin\Oauth2GrantManager;
use Drupal\user\PermissionHandlerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class Scope Form.
 *
 * @ingroup simple_oauth
 */
class Oauth2ScopeForm extends EntityForm {

  /**
   * The scope entity.
   *
   * @var \Drupal\simple_oauth\Entity\Oauth2ScopeEntityInterface
   */
  protected $entity;

  /**
   * The permission handler.
   *
   * @var \Drupal\user\PermissionHandlerInterface
   */
  protected PermissionHandlerInterface $permissionHandler;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    $instance = parent::create($container);
    $instance->permissionHandler = $container->get('user.permissions');

    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state): array {
    $scope = $this->entity;
    $scope_storage = $this->entityTypeManager->getStorage('oauth2_scope');

    $form['name'] = [
      '#type' => 'machine_name',
      '#default_value' => $scope->getName(),
      '#required' => TRUE,
      '#size' => 30,
      '#maxlength' => 64,
      '#machine_name' => [
        'replace_pattern' => '[^a-z0-9_:]+',
        'exists' => [$scope_storage, 'load'],
      ],
      '#description' => $this->t('A unique name for this scope. It must only contain lowercase letters, numbers, underscores, and colons.'),
    ];
    $form['description'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Description'),
      '#required' => TRUE,
      '#default_value' => $scope->getDescription(),
      '#description' => $this->t('Description of the scope.'),
    ];

    $grant_type_options = Oauth2GrantManager::getAvailablePluginsAsOptions();
    $form['grant_types'] = [
      '#type' => 'fieldset',
      '#tree' => TRUE,
      '#required' => TRUE,
      '#title' => $this->t('Grant types'),
      '#description' => $this->t('Enable the scope for specific grant types and optionally give a specific scope description per grant type.'),
    ];
    $grant_types = $scope->getGrantTypes();
    foreach ($grant_type_options as $grant_type_key => $grant_type_label) {
      $form['grant_types'][$grant_type_key] = [
        'status' => [
          '#type' => 'checkbox',
          '#title' => $grant_type_label,
          '#default_value' => $grant_types[$grant_type_key]['status'] ?? FALSE,
        ],
        'description' => [
          '#type' => 'textfield',
          '#title' => $this->t('Description for %grant_type', ['%grant_type' => $grant_type_label]),
          '#default_value' => $grant_types[$grant_type_key]['description'] ?? '',
          '#states' => [
            'visible' => [
              ':input[name="grant_types[' . $grant_type_key . '][status]"]' => ['checked' => TRUE],
            ],
          ],
        ],
      ];
    }

    $form['umbrella'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Umbrella'),
      '#default_value' => $scope->isUmbrella(),
      '#description' => $this->t('An umbrella scope groups multiple scopes.'),
    ];
    $form['parent'] = [
      '#type' => 'select',
      '#title' => $this->t('Parent'),
      '#options' => $this->getParentOptions(),
      '#default_value' => $scope->getParent(),
      '#description' => $this->t('If a client requests the parent scope it also has any children scopes.'),
      '#empty_value' => '_none',
      '#states' => [
        'visible' => [
          '#edit-umbrella' => ['checked' => FALSE],
        ],
      ],
    ];
    $form['granularity'] = [
      '#type' => 'select',
      '#title' => $this->t('Granularity'),
      '#options' => [
        Oauth2ScopeInterface::GRANULARITY_PERMISSION => $this->t('Permission'),
        Oauth2ScopeInterface::GRANULARITY_ROLE => $this->t('Role'),
      ],
      '#default_value' => $scope->getGranularity(),
      '#description' => $this->t('Map scope to a single permission or role.'),
    ];
    $form['permission'] = [
      '#type' => 'select',
      '#title' => $this->t('Permission'),
      '#options' => $this->getPermissionOptions(),
      '#default_value' => $scope->getPermission(),
      '#description' => $this->t('Reference the scope to a permission; this leverages the resource access.'),
      '#states' => [
        'visible' => [
          '#edit-umbrella' => ['checked' => FALSE],
          ':input[name="granularity"]' => ['value' => Oauth2ScopeInterface::GRANULARITY_PERMISSION],
        ],
        'required' => [
          '#edit-umbrella' => ['checked' => FALSE],
          ':input[name="granularity"]' => ['value' => Oauth2ScopeInterface::GRANULARITY_PERMISSION],
        ],
      ],
    ];
    $form['role'] = [
      '#type' => 'select',
      '#title' => $this->t('Role'),
      '#options' => $this->getRoleOptions(),
      '#default_value' => $scope->getRole(),
      '#description' => $this->t('Reference the scope to a permission; this leverages the resource access.'),
      '#states' => [
        'visible' => [
          '#edit-umbrella' => ['checked' => FALSE],
          ':input[name="granularity"]' => ['value' => Oauth2ScopeInterface::GRANULARITY_ROLE],
        ],
        'required' => [
          '#edit-umbrella' => ['checked' => FALSE],
          ':input[name="granularity"]' => ['value' => Oauth2ScopeInterface::GRANULARITY_ROLE],
        ],
      ],
    ];

    return parent::form($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  protected function actions(array $form, FormStateInterface $form_state) {
    $actions = parent::actions($form, $form_state);
    $actions['submit']['#value'] = $this->t('Save scope');
    return $actions;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    parent::validateForm($form, $form_state);

    $grant_types = $form_state->getValue('grant_types');
    $enabled_grant_type = FALSE;
    foreach ($grant_types as $grant_type) {
      if ($grant_type['status']) {
        $enabled_grant_type = TRUE;
      }
    }

    if (!$enabled_grant_type) {
      $form_state->setErrorByName('grant_types', $this->t('Enabling a grant type is required.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    parent::submitForm($form, $form_state);
    $this->messenger()->addMessage($this->t('The scope configuration has been saved.'));
    $form_state->setRedirect('entity.oauth2_scope.collection');
  }

  /**
   * Get the parent scope options.
   *
   * @return array
   *   Returns the parent scope options.
   */
  protected function getParentOptions(): array {
    $options = [];
    /** @var \Drupal\simple_oauth\Entity\Oauth2ScopeEntityInterface[] $scopes */
    $scopes = $this->entityTypeManager->getStorage('oauth2_scope')->loadMultiple();
    foreach ($scopes as $key => $scope) {
      // Exclude current scope and don't allow recursive reference.
      if (
        $this->entity->id() !== $key &&
        ($this->entity->isNew() || $this->entity->id() !== $scope->getParent())
      ) {
        $options[$key] = $scope->getName();
      }
    }

    return $options;
  }

  /**
   * Get the permission options.
   *
   * @return array
   *   Returns the permission options.
   */
  protected function getPermissionOptions(): array {
    $options = [];
    foreach ($this->permissionHandler->getPermissions() as $key => $permission) {
      $provider = $permission['provider'];
      $display_name = $this->moduleHandler->getName($provider);
      $options[$display_name][$key] = strip_tags($permission['title']);
    }

    return $options;
  }

  /**
   * Get the role options.
   *
   * @return array
   *   Returns the role options.
   */
  protected function getRoleOptions(): array {
    $options = [];
    $user_storage = $this->entityTypeManager->getStorage('user_role');
    foreach ($user_storage->loadMultiple() as $role) {
      $options[$role->id()] = $role->label();
    }

    return $options;
  }

}
