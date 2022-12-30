<?php

namespace Drupal\group\Entity\Controller;

use Drupal\group\Entity\GroupTypeInterface;
use Drupal\group\Entity\GroupContentType;
use Drupal\group\Plugin\GroupContentEnablerInterface;
use Drupal\group\Plugin\GroupContentEnablerManagerInterface;
use Drupal\Core\Url;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides the user permissions administration form for a specific group type.
 */
class GroupTypeController extends ControllerBase {

  /**
   * The group type to use in this controller.
   *
   * @var \Drupal\group\Entity\GroupTypeInterface
   */
  protected $groupType;

  /**
   * The group content plugin manager.
   *
   * @var \Drupal\group\Plugin\GroupContentEnablerManagerInterface
   */
  protected $pluginManager;

  /**
   * The module manager.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a new GroupTypeController.
   *
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\group\Plugin\GroupContentEnablerManagerInterface $plugin_manager
   *   The group content plugin manager.
   */
  public function __construct(ModuleHandlerInterface $module_handler, EntityTypeManagerInterface $entity_type_manager, GroupContentEnablerManagerInterface $plugin_manager) {
    $this->moduleHandler = $module_handler;
    $this->entityTypeManager = $entity_type_manager;
    $this->pluginManager = $plugin_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('module_handler'),
      $container->get('entity_type.manager'),
      $container->get('plugin.manager.group_content_enabler')
    );
  }

  /**
   * Builds an admin interface to manage the group type's group content plugins.
   *
   * @param \Drupal\group\Entity\GroupTypeInterface $group_type
   *   The group type to build an interface for.
   *
   * @return array
   *   The render array for the page.
   */
  public function content(GroupTypeInterface $group_type) {
    $this->groupType = $group_type;

    $rows['installed'] = $rows['available'] = [];
    $installed_ids = $this->pluginManager->getInstalledIds($group_type);
    foreach ($this->pluginManager->getAll() as $plugin_id => $plugin) {
      $is_installed = FALSE;

      // If the plugin is installed on the group type, use that one instead of
      // an 'empty' version so that we may use methods on it which expect to
      // have a group type configured.
      if (in_array($plugin_id, $installed_ids)) {
        $plugin = $this->groupType->getContentPlugin($plugin_id);
        $is_installed = TRUE;
      }

      $status = $is_installed ? 'installed' : 'available';
      $rows[$status][$plugin_id] = $this->buildRow($plugin, $is_installed);
    }

    $page['information'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Information about content plugins'),
    ];

    $page['information']['intro']['#markup'] = $this->t('<p>In order to be able to add entities as content to groups of this group type, a so-called content plugin needs to be installed. This plugin informs the Group module on how the entity type can be added to a group, what rules apply and whether it should control access over said entity type. When a plugin is installed, you should check out its configuration form to see what options are available to further customize the plugin behavior.</p>');
    $page['information']['fields']['#markup'] = $this->t('<p>Should you choose to show the relationship entities that track which entity belongs to which group or should the module that provided the module enforce this, you can control which fields are available on that relation entity and how they are presented in the front-end.</p>');
    $page['information']['install_types'] = [
      '#theme' => 'item_list',
      '#items' => [
        $this->t('<strong>Manual</strong> content plugins can be (un)installed freely by the user'),
        $this->t('<strong>Code-only</strong> content plugins can only be (un)installed through code, this is often done when certain conditions are met in the module that provided the plugin'),
        $this->t('<strong>Enforced</strong> content plugins are always enabled and cannot be uninstalled'),
      ],
      '#prefix' => $this->t('<p>The following installation types are available:</p>'),
    ];

    $page['system_compact_link'] = [
      '#id' => FALSE,
      '#type' => 'system_compact_link',
    ];

    $page['content'] = [
      '#type' => 'table',
      '#header' => [
        'info' => $this->t('Plugin information'),
        'provider' => $this->t('Provided by'),
        'entity_type_id' => $this->t('Applies to'),
        'status' => $this->t('Status'),
        'install_type' => $this->t('Installation type'),
        'operations' => $this->t('Operations'),
      ],
    ];
    $page['content'] += $rows['installed'];
    $page['content'] += $rows['available'];

    return $page;
  }

  /**
   * Builds a row for a content enabler plugin.
   *
   * @param \Drupal\group\Plugin\GroupContentEnablerInterface $plugin
   *   The content enabler plugin to build operation links for.
   * @param bool $is_installed
   *   Whether the plugin is installed.
   *
   * @return array
   *   A render array to use as a table row.
   */
  public function buildRow(GroupContentEnablerInterface $plugin, $is_installed) {
    $status = $is_installed ? $this->t('Installed') : $this->t('Available');

    $install_type = $this->t('Manual');
    if ($plugin->isEnforced()) {
      $install_type = $this->t('Enforced');
    }
    elseif ($plugin->isCodeOnly()) {
      $install_type = $this->t('Code-only');
    }

    $row = [
      'info' => [
        '#type' => 'inline_template',
        '#template' => '<div class="description"><span class="label">{{ label }}</span>{% if description %}<br/>{{ description }}{% endif %}</div>',
        '#context' => [
          'label' => $plugin->getLabel(),
        ],
      ],
      'provider' => [
        '#markup' => $this->moduleHandler->getName($plugin->getProvider())
      ],
      'entity_type_id' => [
        '#markup' => $this->entityTypeManager->getDefinition($plugin->getEntityTypeId())->getLabel()
      ],
      'status' => ['#markup' => $status],
      'install_type' => ['#markup' => $install_type],
      'operations' => $this->buildOperations($plugin, $is_installed),
    ];

    // Show the content enabler description if toggled on.
    if (!system_admin_compact_mode()) {
      $row['info']['#context']['description'] = $plugin->getDescription();
    }

    return $row;
  }

  /**
   * Provides an array of information to build a list of operation links.
   *
   * @param \Drupal\group\Plugin\GroupContentEnablerInterface $plugin
   *   The content enabler plugin to build operation links for.
   * @param bool $is_installed
   *   Whether the plugin is installed.
   *
   * @return array
   *   An associative array of operation links for the group type's content
   *   plugin, keyed by operation name, containing the following key-value pairs:
   *   - title: The localized title of the operation.
   *   - url: An instance of \Drupal\Core\Url for the operation URL.
   *   - weight: The weight of this operation.
   */
  public function getOperations($plugin, $is_installed) {
    return $plugin->getOperations() + $this->getDefaultOperations($plugin, $is_installed);
  }

  /**
   * Gets the group type's content plugin's default operation links.
   *
   * @param \Drupal\group\Plugin\GroupContentEnablerInterface $plugin
   *   The content enabler plugin to build operation links for.
   * @param bool $is_installed
   *   Whether the plugin is installed.
   *
   * @return array
   *   The array structure is identical to the return value of
   *   self::getOperations().
   */
  protected function getDefaultOperations($plugin, $is_installed) {
    $operations = [];

    $plugin_id = $plugin->getPluginId();
    $ui_allowed = !$plugin->isEnforced() && !$plugin->isCodeOnly();

    if ($is_installed) {
      /** @var \Drupal\group\Entity\GroupContentTypeInterface $group_content_type */
      $group_content_type_id = $plugin->getContentTypeConfigId();
      $group_content_type = GroupContentType::load($group_content_type_id);

      $route_params = [
        'group_content_type' => $group_content_type_id,
      ];

      $operations['configure'] = [
        'title' => $this->t('Configure'),
        'url' => new Url('entity.group_content_type.edit_form', $route_params),
      ];

      if ($ui_allowed) {
        $operations['uninstall'] = [
          'title' => $this->t('Uninstall'),
          'weight' => 99,
          'url' => new Url('entity.group_content_type.delete_form', $route_params),
        ];
      }

      if ($this->moduleHandler->moduleExists('field_ui')) {
        $operations += field_ui_entity_operation($group_content_type);
      }
    }
    elseif ($ui_allowed) {
      $operations['install'] = [
        'title' => $this->t('Install'),
        'url' => new Url('entity.group_content_type.add_form', ['group_type' => $this->groupType->id(), 'plugin_id' => $plugin_id]),
      ];
    }

    return $operations;
  }

  /**
   * Builds operation links for the group type's content plugins.
   *
   * @param \Drupal\group\Plugin\GroupContentEnablerInterface $plugin
   *   The content enabler plugin to build operation links for.
   * @param bool $is_installed
   *   Whether the plugin is installed.
   *
   * @return array
   *   A render array of operation links.
   */
  public function buildOperations($plugin, $is_installed) {
    $build = [
      '#type' => 'operations',
      '#links' => $this->getOperations($plugin, $is_installed),
    ];
    uasort($build['#links'], '\Drupal\Component\Utility\SortArray::sortByWeightElement');
    return $build;
  }

}
