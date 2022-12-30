<?php

namespace Drupal\group\Entity\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\group\Plugin\GroupContentEnablerManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form controller for group content type forms.
 */
class GroupContentTypeForm extends EntityForm {

  /**
   * The group content enabler plugin manager.
   *
   * @var \Drupal\group\Plugin\GroupContentEnablerManagerInterface
   */
  protected $pluginManager;

  /**
   * Constructs a new GroupContentTypeForm.
   *
   * @param \Drupal\group\Plugin\GroupContentEnablerManagerInterface $plugin_manager
   *   The group content plugin manager.
   */
  public function __construct(GroupContentEnablerManagerInterface $plugin_manager) {
    $this->pluginManager = $plugin_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin.manager.group_content_enabler')
    );
  }

  /**
   * Returns the configurable plugin for the group content type.
   *
   * @return \Drupal\group\Plugin\GroupContentEnablerInterface
   *   The configurable group content enabler plugin.
   */
  protected function getContentPlugin() {
    /** @var \Drupal\group\Entity\GroupContentTypeInterface $group_content_type */
    $group_content_type = $this->getEntity();
    $group_type = $group_content_type->getGroupType();

    // Initialize an empty plugin so we can show a default configuration form.
    if ($this->operation == 'add') {
      $plugin_id = $group_content_type->getContentPluginId();
      $configuration['group_type_id'] = $group_type->id();
      return $this->pluginManager->createInstance($plugin_id, $configuration);
    }
    // Return the already configured plugin for existing group content types.
    else {
      return $group_content_type->getContentPlugin();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    /** @var \Drupal\group\Entity\GroupContentTypeInterface $group_content_type */
    $group_content_type = $this->getEntity();
    $group_type = $group_content_type->getGroupType();
    $plugin = $this->getContentPlugin();

    // @todo These messages may need some love.
    if ($this->operation == 'add') {
      $form['#title'] = $this->t('Install content plugin');
      $message = 'By installing the %plugin plugin, you will allow %entity_type entities to be added to groups of type %group_type';
    }
    else {
      $form['#title'] = $this->t('Configure content plugin');
      $message = 'This form allows you to configure the %plugin plugin for the %group_type group type.';
    }

    // Add in the replacements for the $message variable set above.
    $replace = [
      '%plugin' => $plugin->getLabel(),
      '%entity_type' => $this->entityTypeManager->getDefinition($plugin->getEntityTypeId())->getLabel(),
      '%group_type' => $group_type->label(),
    ];

    // Display a description to explain the purpose of the form.
    $form['description'] = [
      '#markup' => $this->t($message, $replace),
    ];

    // Add in the plugin configuration form.
    $form += $plugin->buildConfigurationForm($form, $form_state);

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  protected function actions(array $form, FormStateInterface $form_state) {
    $actions['submit'] = [
      '#type' => 'submit',
      '#value' => $this->operation == 'add' ? $this->t('Install plugin') : $this->t('Save configuration'),
      '#submit' => ['::submitForm'],
    ];

    return $actions;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $plugin = $this->getContentPlugin();
    $plugin->validateConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    /** @var \Drupal\group\Entity\GroupContentTypeInterface $group_content_type */
    $group_content_type = $this->getEntity();
    $group_type = $group_content_type->getGroupType();
    $plugin = $this->getContentPlugin();
    $plugin->submitConfigurationForm($form, $form_state);

    // Remove button and internal Form API values from submitted values.
    $form_state->cleanValues();

    // Extract the values as configuration that should be saved.
    $config = $form_state->getValues();

    // If we are on an 'add' form, we create the group content type using the
    // plugin configuration submitted using this form.
    if ($this->operation == 'add') {
      /** @var \Drupal\group\Entity\Storage\GroupContentTypeStorageInterface $storage */
      $storage = $this->entityTypeManager->getStorage('group_content_type');
      $storage->createFromPlugin($group_type, $plugin->getPluginId(), $config)->save();
      $this->messenger()->addStatus($this->t('The content plugin was installed on the group type.'));
    }
    // Otherwise, we update the existing group content type's configuration.
    else {
      $group_content_type->updateContentPlugin($config);
      $this->messenger()->addStatus($this->t('The content plugin configuration was saved.'));
    }

    $form_state->setRedirect('entity.group_type.content_plugins', ['group_type' => $group_type->id()]);
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
    if ($route_match->getRawParameter('group_type') !== NULL && $route_match->getRawParameter('plugin_id') !== NULL) {
      $values = [
        'group_type' => $route_match->getRawParameter('group_type'),
        'content_plugin' => $route_match->getRawParameter('plugin_id'),
      ];
    }
    return $this->entityTypeManager->getStorage($entity_type_id)->create($values);
  }

}
