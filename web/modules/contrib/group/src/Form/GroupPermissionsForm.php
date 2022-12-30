<?php

namespace Drupal\group\Form;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\group\Access\GroupPermissionHandlerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides the group permissions administration form.
 */
abstract class GroupPermissionsForm extends FormBase {

  /**
   * The permission handler.
   *
   * @var \Drupal\group\Access\GroupPermissionHandlerInterface
   */
  protected $groupPermissionHandler;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * Constructs a new GroupPermissionsForm.
   *
   * @param \Drupal\group\Access\GroupPermissionHandlerInterface $permission_handler
   *   The group permission handler.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   */
  public function __construct(GroupPermissionHandlerInterface $permission_handler, ModuleHandlerInterface $module_handler) {
    $this->groupPermissionHandler = $permission_handler;
    $this->moduleHandler = $module_handler;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('group.permissions'),
      $container->get('module_handler')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'group_admin_permissions';
  }

  /**
   * Gets a few basic instructions to show the user.
   *
   * @return array
   *   A render array to display atop the form.
   */
  protected function getInfo() {
    // Format a message explaining the cells with a red x inside them.
    $replace = ['@red_dash' => new FormattableMarkup('<span style="color: #ff0000;">-</span>', [])];
    $message =  $this->t('Cells with a @red_dash indicate that the permission is not available for that role.', $replace);

    // We use FormattableMarkup so the 'style' attribute doesn't get escaped.
    return ['red_dash_info' => ['#markup' => new FormattableMarkup("<p>$message</p>", [])]];
  }

  /**
   * Gets the group type to build the form for.
   *
   * @return \Drupal\group\Entity\GroupTypeInterface
   *   The group type some or more roles belong to.
   */
  abstract protected function getGroupType();

  /**
   * Gets the group roles to display in this form.
   *
   * @return \Drupal\group\Entity\GroupRoleInterface[]
   *   An array of group role objects.
   */
  protected function getGroupRoles() {
    return [];
  }

  /**
   * Gets the permissions to display in this form.
   *
   * @return array
   *   An multidimensional associative array of permissions, keyed by the
   *   providing module first and then by permission name.
   */
  protected function getPermissions() {
    $by_provider_and_section = [];

    // Create a list of group permissions ordered by their provider and section.
    foreach ($this->groupPermissionHandler->getPermissionsByGroupType($this->getGroupType()) as $permission_name => $permission) {
      $by_provider_and_section[$permission['provider']][$permission['section_id']][$permission_name] = $permission;
    }

    // Always put the 'general' section at the top if provided.
    foreach ($by_provider_and_section as $provider => $sections) {
      if (isset($sections['general'])) {
        $by_provider_and_section[$provider] = ['general' => $sections['general']] + $sections;
      }
    }

    return $by_provider_and_section;
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $role_info = [];

    // Sort the group roles using the static sort() method.
    // See \Drupal\Core\Config\Entity\ConfigEntityBase::sort().
    $group_roles = $this->getGroupRoles();
    uasort($group_roles, '\Drupal\group\Entity\GroupRole::sort');

    // Retrieve information for every role to user further down. We do this to
    // prevent the same methods from being fired (rows * permissions) times.
    foreach ($group_roles as $role_name => $group_role) {
      $role_info[$role_name] = [
        'label' => $group_role->label(),
        'permissions' => $group_role->getPermissions(),
        'is_anonymous' => $group_role->isAnonymous(),
        'is_outsider' => $group_role->isOutsider(),
        'is_member' => $group_role->isMember(),
      ];
    }

    // Render the general information.
    if ($info = $this->getInfo()) {
      $form['info'] = $info;
    }

    // Render the link for hiding descriptions.
    $form['system_compact_link'] = [
      '#id' => FALSE,
      '#type' => 'system_compact_link',
    ];

    // Render the roles and permissions table.
    $form['permissions'] = [
      '#type' => 'table',
      '#header' => [$this->t('Permission')],
      '#id' => 'permissions',
      '#attributes' => ['class' => ['permissions', 'js-permissions']],
      '#sticky' => TRUE,
    ];

    // Create a column with header for every group role.
    foreach ($role_info as $info) {
      $form['permissions']['#header'][] = [
        'data' => $info['label'],
        'class' => ['checkbox'],
      ];
    }

    // Render the permission as sections of rows.
    $hide_descriptions = system_admin_compact_mode();
    foreach ($this->getPermissions() as $provider => $sections) {
      // Print a full width row containing the provider name for each provider.
      $form['permissions'][$provider] = [
        [
          '#wrapper_attributes' => [
            'colspan' => count($group_roles) + 1,
            'class' => ['module'],
            'id' => 'module-' . $provider,
          ],
          '#markup' => $this->moduleHandler->getName($provider),
        ]
      ];

      foreach ($sections as $section_id => $permissions) {
        // Start each section with a full width row containing the section name.
        $form['permissions'][$section_id] = [
          [
            '#wrapper_attributes' => [
              'colspan' => count($group_roles) + 1,
              'class' => ['section'],
              'id' => 'section-' . $section_id,
            ],
            '#markup' => reset($permissions)['section'],
          ]
        ];

        // Then list all of the permissions for that provider and section.
        foreach ($permissions as $perm => $perm_item) {
          // Create a row for the permission, starting with the description cell.
          $form['permissions'][$perm]['description'] = [
            '#type' => 'inline_template',
            '#template' => '<span class="title">{{ title }}</span>{% if description or warning %}<div class="description">{% if warning %}<em class="permission-warning">{{ warning }}</em><br />{% endif %}{{ description }}</div>{% endif %}',
            '#context' => [
              'title' => $perm_item['title'],
            ],
            '#wrapper_attributes' => [
              'class' => ['permission'],
            ],
          ];

          // Show the permission description and warning if toggled on.
          if (!$hide_descriptions) {
            $form['permissions'][$perm]['description']['#context']['description'] = $perm_item['description'];
            $form['permissions'][$perm]['description']['#context']['warning'] = $perm_item['warning'];
          }

          // Finally build a checkbox cell for every group role.
          foreach ($role_info as $role_name => $info) {
            // Determine whether the permission is available for this role.
            $na = $info['is_anonymous'] && !in_array('anonymous', $perm_item['allowed for']);
            $na = $na || ($info['is_outsider'] && !in_array('outsider', $perm_item['allowed for']));
            $na = $na || ($info['is_member'] && !in_array('member', $perm_item['allowed for']));

            // Show a red '-' if the permission is unavailable.
            if ($na) {
              $form['permissions'][$perm][$role_name] = [
                '#title' => $info['label'] . ': ' . $perm_item['title'],
                '#title_display' => 'invisible',
                '#wrapper_attributes' => [
                  'class' => ['checkbox'],
                  'style' => 'color: #ff0000;',
                ],
                '#markup' => '-',
              ];
            }
            // Show a checkbox if the permissions is available.
            else {
              $form['permissions'][$perm][$role_name] = [
                '#title' => $info['label'] . ': ' . $perm_item['title'],
                '#title_display' => 'invisible',
                '#wrapper_attributes' => [
                  'class' => ['checkbox'],
                ],
                '#type' => 'checkbox',
                '#default_value' => in_array($perm, $info['permissions']) ? 1 : 0,
                '#attributes' => [
                  'class' => [
                    'rid-' . $role_name,
                    'js-rid-' . $role_name
                  ]
                ],
                '#parents' => [$role_name, $perm],
              ];
            }
          }
        }
      }
    }

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save permissions'),
      '#button_type' => 'primary',
    ];

    // @todo Do something like the global permissions page JS for 'member'.
    // @todo See user/drupal.user.permissions for JS example.
    $form['#attached']['library'][] = 'group/permissions';

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  function submitForm(array &$form, FormStateInterface $form_state) {
    foreach ($this->getGroupRoles() as $role_name => $group_role) {
      /** @var \Drupal\group\Entity\GroupRoleInterface $group_role */
      $permissions = $form_state->getValue($role_name);
      $group_role->changePermissions($permissions)->trustData()->save();
    }

    $this->messenger()->addStatus($this->t('The changes have been saved.'));
  }

}
