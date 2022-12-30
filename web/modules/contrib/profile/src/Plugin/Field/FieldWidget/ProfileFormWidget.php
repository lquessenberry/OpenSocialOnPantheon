<?php

namespace Drupal\profile\Plugin\Field\FieldWidget;

use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\profile\Entity\ProfileInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the 'profile_form' widget.
 *
 * @FieldWidget(
 *   id = "profile_form",
 *   label = @Translation("Profile form"),
 *   field_types = {
 *     "entity_reference"
 *   }
 * )
 */
class ProfileFormWidget extends WidgetBase implements ContainerFactoryPluginInterface {

  /**
   * The entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The entity display repository.
   *
   * @var \Drupal\Core\Entity\EntityDisplayRepositoryInterface
   */
  protected $entityDisplayRepository;

  /**
   * Constructs a new ProfileFormWidget object.
   *
   * @param string $plugin_id
   *   The plugin_id for the widget.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The definition of the field to which the widget is associated.
   * @param array $settings
   *   The widget settings.
   * @param array $third_party_settings
   *   Any third party settings.
   * @param \Drupal\core\Entity\EntityFieldManagerInterface $entity_field_manager
   *   The entity field manager.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Entity\EntityDisplayRepositoryInterface $entity_display_repository
   *   The entity display repository.
   */
  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, array $third_party_settings, EntityFieldManagerInterface $entity_field_manager, EntityTypeManagerInterface $entity_type_manager, EntityDisplayRepositoryInterface $entity_display_repository) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $third_party_settings);

    $this->entityFieldManager = $entity_field_manager;
    $this->entityTypeManager = $entity_type_manager;
    $this->entityDisplayRepository = $entity_display_repository;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['third_party_settings'],
      $container->get('entity_field.manager'),
      $container->get('entity_type.manager'),
      $container->get('entity_display.repository')
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'form_mode' => 'default',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $form_modes = $this->entityDisplayRepository->getFormModeOptions($this->getFieldSetting('target_type'));
    $element['form_mode'] = [
      '#type' => 'select',
      '#options' => $form_modes,
      '#title' => $this->t('Form mode'),
      '#default_value' => $this->getSetting('form_mode'),
      '#required' => TRUE,
    ];

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $form_modes = $this->entityDisplayRepository->getFormModeOptions($this->getFieldSetting('target_type'));
    $form_mode = $this->getSetting('form_mode');
    $form_mode = isset($form_modes[$form_mode]) ? $form_modes[$form_mode] : $form_mode;
    $summary = [];
    $summary[] = $this->t('Form mode: @mode', ['@mode' => $form_mode]);

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function form(FieldItemListInterface $items, array &$form, FormStateInterface $form_state, $get_delta = NULL) {
    // Do not allow this widget to be used as a default value widget.
    if ($this->isDefaultValueWidget($form_state)) {
      return [];
    }

    return parent::form($items, $form, $form_state, $get_delta);
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    /** @var \Drupal\user\UserInterface $account */
    $account = $items->getEntity();
    /** @var \Drupal\profile\ProfileStorageInterface $profile_storage */
    $profile_storage = $this->entityTypeManager->getStorage('profile');
    $profile_type_storage = $this->entityTypeManager->getStorage('profile_type');
    /** @var \Drupal\profile\Entity\ProfileTypeInterface $profile_type */
    $profile_type = $profile_type_storage->load($this->getFieldSetting('profile_type'));
    $property = ['profiles', $profile_type->id()];
    $profile = $form_state->get($property);
    if (!$profile) {
      if (!$account->isAnonymous()) {
        $profile = $profile_storage->loadByUser($account, $profile_type->id());
      }
      if (!$profile) {
        $profile = $profile_storage->create([
          'type' => $profile_type->id(),
        ]);
      }
      $form_state->set($property, $profile);
    }
    // Adding/editing profiles for existing users needs to respect access.
    if (!$account->isNew()) {
      $access_control_handler = $this->entityTypeManager->getAccessControlHandler('profile');
      if ($profile->isNew()) {
        $access = $access_control_handler->createAccess($profile_type->id(), NULL, [
          'profile_owner' => $account,
        ]);
      }
      else {
        $access = $access_control_handler->access($profile, 'update');
      }

      if (!$access) {
        $element['#access'] = FALSE;
        return $element;
      }
    }

    $element = [
      '#type' => 'details',
      '#description' => '',
      '#open' => TRUE,
      // Remove the "required" clue, it's display-only and confusing.
      '#required' => FALSE,
      '#field_title' => $profile_type->getDisplayLabel() ?: $profile_type->label(),
      '#after_build' => [
        [get_class($this), 'removeTranslatabilityClue'],
      ],
    ] + $element;

    $form_mode = $this->getSetting('form_mode');
    $element['entity'] = [
      '#parents' => array_merge($element['#field_parents'], [
        $items->getName(), $delta, 'entity',
      ]),
      '#bundle' => $profile->bundle(),
      '#element_validate' => [
        [get_class($this), 'validateProfileForm'],
      ],
      '#form_mode' => $form_mode,
    ];

    if (function_exists('field_group_attach_groups')) {
      $context = [
        'entity_type' => $profile->getEntityTypeId(),
        'bundle' => $profile->bundle(),
        'entity' => $profile,
        'context' => 'form',
        'display_context' => 'form',
        'mode' => $form_mode,
      ];
      field_group_attach_groups($element['entity'], $context);
      $element['entity']['#process'][] = 'field_group_form_process';
    }

    $form_display = EntityFormDisplay::collectRenderDisplay($profile, $form_mode);
    $form_display->removeComponent('revision_log_message');
    $form_display->buildForm($profile, $element['entity'], $form_state);

    $form_process_callback = [get_class($this), 'attachSubmit'];
    // Make sure the #process callback doesn't get added more than once
    // if the widget is used on multiple fields.
    if (!isset($form['#process']) || !in_array($form_process_callback, $form['#process'])) {
      $form['#process'][] = [get_class($this), 'attachSubmit'];
    }

    return $element;
  }

  /**
   * After-build callback for removing the translatability clue from the widget.
   *
   * @see ContentTranslationHandler::addTranslatabilityClue()
   */
  public static function removeTranslatabilityClue(array $element, FormStateInterface $form_state) {
    $element['#title'] = $element['#field_title'];
    return $element;
  }

  /**
   * Process callback: Adds the widget's submit handler.
   */
  public static function attachSubmit(array $form, FormStateInterface $form_state) {
    $form['actions']['submit']['#submit'][] = [static::class, 'saveProfiles'];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function extractFormValues(FieldItemListInterface $items, array $form, FormStateInterface $form_state) {
    if ($this->isDefaultValueWidget($form_state)) {
      $items->filterEmptyItems();
      return;
    }
    $property = ['profiles', $this->getFieldSetting('profile_type')];
    $profile = $form_state->get($property);
    if (!empty($profile)) {
      $values = [
        ['entity' => $profile],
      ];
      $items->setValue($values);
      $items->filterEmptyItems();
    }
  }

  /**
   * Validates the profile form.
   *
   * @param array $element
   *   The profile form element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public static function validateProfileForm(array &$element, FormStateInterface $form_state) {
    /** @var \Drupal\profile\Entity\ProfileInterface $profile */
    $property = ['profiles', $element['#bundle']];
    $profile = $form_state->get($property);
    if (!empty($profile)) {
      assert($profile instanceof ProfileInterface);
      $form_display = EntityFormDisplay::collectRenderDisplay($profile, $element['#form_mode']);
      $form_display->extractFormValues($profile, $element, $form_state);
      $form_display->validateFormValues($profile, $element, $form_state);
    }
  }

  /**
   * Completes and saves all profiles.
   *
   * @param array $form
   *   The complete form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public static function saveProfiles(array $form, FormStateInterface $form_state) {
    /** @var \Drupal\Core\Session\AccountInterface $account */
    $account = $form_state->getFormObject()->getEntity();
    if (!$account) {
      return;
    }
    $profiles = $form_state->get('profiles');
    foreach ($profiles as $profile) {
      assert($profile instanceof ProfileInterface);
      $profile->setOwnerId($account->id());
      $profile->setPublished();
      $profile->save();
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function isApplicable(FieldDefinitionInterface $field_definition) {
    $entity_type = $field_definition->getTargetEntityTypeId();
    return $entity_type == 'user' && $field_definition->getSetting('target_type') == 'profile' && $field_definition->isComputed();
  }

}
