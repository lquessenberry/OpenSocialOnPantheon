<?php

namespace Drupal\flag\Plugin\Flag;

use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\flag\FlagType\FlagTypeBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Provides a flag type for all entity types.
 *
 * Base entity flag handler.
 *
 * @FlagType(
 *   id = "entity",
 *   title = @Translation("Flag Type Entity"),
 *   deriver = "Drupal\flag\Plugin\Derivative\EntityFlagTypeDeriver"
 * )
 */
class EntityFlagType extends FlagTypeBase {

  use StringTranslationTrait;

  /**
   * The entity type defined in plugin definition.
   *
   * @var string
   */
  protected $entityType = '';

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, array $plugin_definition, ModuleHandlerInterface $module_handler) {
    $this->entityType = $plugin_definition['entity_type'];
    parent::__construct($configuration, $plugin_id, $plugin_definition, $module_handler);
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    $options = parent::defaultConfiguration();
    $options += [
      // Output the flag in the entity links.
      // This is empty for now and will get overriden for different
      // entities.
      // @see hook_entity_view().
      'show_in_links' => [],
      // Output the flag as individual fields.
      'show_as_field' => TRUE,
      // Add a checkbox for the flag in the entity form.
      // @see hook_field_attach_form().
      'show_on_form' => FALSE,
      'show_contextual_link' => FALSE,
    ];
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {


    $form['display']['show_as_field'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Display link as field'),
      '#description' => $this->t('Show the flag link as a field, which can be ordered among other entity elements in the "Manage display" settings for the entity type.'),
      '#default_value' => $this->showAsField(),
    ];
    /*
    if (empty($entity_info['fieldable'])) {
      $form['display']['show_as_field']['#disabled'] = TRUE;
      $form['display']['show_as_field']['#description'] = $this->t("This entity type is not fieldable.");
    }
    */
    $form['display']['show_on_form'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Display checkbox on entity edit form'),
      '#default_value' => $this->showOnForm(),
      '#weight' => 5,
    ];

    // We use FieldAPI to put the flag checkbox on the entity form, so therefore
    // require the entity to be fielable. Since this is a potential DX
    // headscratcher for a developer wondering where this option has gone,
    // we disable it and explain why.
    /*
    if (empty($entity_info['fieldable'])) {
      $form['display']['show_on_form']['#disabled'] = TRUE;
      $form['display']['show_on_form']['#description'] = $this->t('This is only possible on entities which are fieldable.');
    }
    */
    $form['display']['show_contextual_link'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Display in contextual links'),
      '#default_value' => $this->showContextualLink(),
      '#description' => $this->t("Note that not all entity types support contextual links.
        <br/>
        <strong>Warning: </strong>Due to how contextual links are cached on frontend
        we have to set max-age as 0 for entity cache if
        user has access to contextual links and to this flag. This means that
        those users will get no cache hits for render elements rendering flaggable
        entities with contextual links."),
      '#access' => \Drupal::moduleHandler()->moduleExists('contextual'),
      '#weight' => 10,
    ];

    // Add checkboxes to show flag link on each entity view mode.
    $options = [];
    $defaults = [];

    /* @var \Drupal\Core\Entity\EntityDisplayRepositoryInterface $entity_display_service */
    $entity_display_service = \Drupal::service('entity_display.repository');
    $view_modes = $entity_display_service->getViewModes($this->entityType);

    foreach ($view_modes as $name => $view_mode) {
      $options[$name] = $this->t('Display on @name view mode', ['@name' => $view_mode['label']]);
      if ($this->showInLinks($name)) {
        $defaults[$name] = $name;
      }
    }

    $form['display']['show_in_links'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Display in entity links'),
      '#description' => $this->t('Show the flag link with the other links on the entity.'),
      '#options' => $options,
      '#default_value' => $defaults,
      '#weight' => 15,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);
    $this->configuration['show_in_links'] = array_filter($form_state->getValue('show_in_links'));
    $this->configuration['show_as_field'] = $form_state->getValue('show_as_field');
    $this->configuration['show_on_form'] = $form_state->getValue('show_on_form');
    $this->configuration['show_contextual_link'] = $form_state->getValue('show_contextual_link');
  }

  /**
   * Return the show in links setting given a view mode.
   *
   * @param string $name
   *   The name of the view mode.
   *
   * @return boolean
   *   TRUE if the flag should appear in the entity links for the view mode.
   */
  public function showInLinks($name) {
    if (!empty($this->configuration['show_in_links'][$name])) {
      return TRUE;
    }

    return FALSE;
  }

  /**
   * Returns the show as field setting.
   *
   * @return bool
   *   TRUE if the flag should appear as a psudofield, FALSE otherwise.
   */
  public function showAsField() {
    return $this->configuration['show_as_field'];
  }

  /**
   * Returns the show on form setting.
   *
   * @return bool
   *   TRUE if the flag should appear on the entity form, FALSE otherwise.
   */
  public function showOnForm() {
    return $this->configuration['show_on_form'];
  }

  /**
   * Returns the show on contextual link setting.
   *
   * @return bool
   *   TRUE if the flag should appear in contextual links, FALSE otherwise.
   */
  public function showContextualLink() {
    return $this->configuration['show_contextual_link'];
  }
}
