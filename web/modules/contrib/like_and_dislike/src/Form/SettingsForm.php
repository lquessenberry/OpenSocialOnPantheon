<?php

namespace Drupal\like_and_dislike\Form;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;

/**
 * Class SettingsForm.
 *
 * @package Drupal\like_and_dislike\Form
 *
 * @ingroup like_and_dislike
 */
class SettingsForm extends ConfigFormBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * The bundle info service.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface
   */
  protected $bundleInfoService;

  /**
   * Constructs a \Drupal\like_and_dislike\Form\SettingsForm object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager .
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager
   *   The entity field manager.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $bundle_info_service
   *   The bundle info service.
   */
  public function __construct(ConfigFactoryInterface $config_factory, EntityTypeManagerInterface $entity_type_manager, EntityFieldManagerInterface $entity_field_manager, EntityTypeBundleInfoInterface $bundle_info_service) {
    parent::__construct($config_factory);

    $this->entityTypeManager = $entity_type_manager;
    $this->entityFieldManager = $entity_field_manager;
    $this->bundleInfoService = $bundle_info_service;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('entity_type.manager'),
      $container->get('entity_field.manager'),
      $container->get('entity_type.bundle.info')
    );
  }

  /**
   * Returns a unique string identifying the form.
   *
   * @return string
   *   The unique string identifying the form.
   */
  public function getFormId() {
    return 'like_and_dislike_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['like_and_dislike.settings'];
  }

  /**
   * Defines the settings form for each entity type bundles.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return array
   *   Form definition array.
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('like_and_dislike.settings');

    $form['enabled_types'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Entity types with Like & Dislike widgets enabled'),
      '#description' => $this->t('If you disable any type here, already existing data will remain untouched.'),
      '#tree' => TRUE,
    ];

    foreach ($this->entityTypeManager->getDefinitions() as $entity_type_id => $entity_type) {
      // Only display content entities with a view builder.
      if ($entity_type->getGroup() != 'content' || !$entity_type->hasHandlerClass('view_builder')) {
        continue;
      }

      $form['enabled_types'][$entity_type_id] = [
        '#type' => 'container',
        '#tree' => TRUE,
      ];
      // Checkbox to enable and disable the entity type.
      $form['enabled_types'][$entity_type_id]['enabled'] = [
        '#type' => 'checkbox',
        '#title' => $entity_type->getLabel(),
        '#default_value' => is_array($config->get('enabled_types.' . $entity_type_id)),
      ];

      // Display entity type bundles.
      if ($entity_type->hasKey('bundle')) {
        $bundles = $this->bundleInfoService->getBundleInfo($entity_type_id);
        // Get bundle label.
        $bundles = array_map(function ($bundle_info) {
          return $bundle_info['label'];
        }, $bundles);

        $form['enabled_types'][$entity_type_id]['bundle_info'] = [
          '#title' => $this->getBundleTypeLabel($entity_type),
          '#type' => 'details',
          '#open' => TRUE,
          '#states' => [
            'invisible' => [
              'input[name="enabled_types[' . $entity_type_id . '][enabled]"]' => ['checked' => FALSE],
            ],
          ],
        ];
        $form['enabled_types'][$entity_type_id]['bundle_info']['bundles'] = [
          '#type' => 'checkboxes',
          '#options' => $bundles,
          '#default_value' => $config->get('enabled_types.' . $entity_type_id) ?: [],
        ];
      }
    }

    // Checkbox to allow vote cancellation.
    $form['allow_cancel_vote'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Allow vote cancellation'),
      '#description' => $this->t('Whether the users should be allowed to cancel their own votes by voting again for the same choice of the same poll.'),
      '#default_value' => $config->get('allow_cancel_vote'),
    ];

    // Checkbox to allow hiding of vote widgets on missing permission.
    $form['hide_vote_widget'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Hide vote widget instead of disabling it'),
      '#description' => $this->t('If checked then instead of disabled widget user will not see widget at all if vote permission is missing.'),
      '#default_value' => $config->get('hide_vote_widget'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('like_and_dislike.settings');
    $enabled_types = [];

    foreach ($form_state->getValue('enabled_types') as $entity_type_id => $entity_type_settings) {
      // Skip disabled content entity types.
      if (!$entity_type_settings['enabled']) {
        continue;
      }

      // Put enabled content entity type into configuration, only save entity
      // types with bundles if at least one bundle is checked.
      if (isset($entity_type_settings['bundle_info']) && array_keys(array_filter($entity_type_settings['bundle_info']['bundles']))) {
        // Filter-out non-selected bundles.
        $enabled_types[$entity_type_id] = array_keys(array_filter($entity_type_settings['bundle_info']['bundles']));
      }
      else {
        $enabled_types[$entity_type_id] = [];
      }
    }

    $config->set('enabled_types', $enabled_types);
    $config->set('allow_cancel_vote', $form_state->getValue('allow_cancel_vote'));
    $config->set('hide_vote_widget', $form_state->getValue('hide_vote_widget'));
    $config->save();

    parent::submitForm($form, $form_state);

    // Settings changes might not immediately be updated for the view display.
    // Thus clear the entity extra field caches.
    $this->entityFieldManager->clearCachedFieldDefinitions();
  }

  /**
   * Returns the bundle type label for a given entity type.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type.
   *
   * @return string
   *   The bundle type label.
   */
  protected function getBundleTypeLabel(EntityTypeInterface $entity_type) {
    if ($bundle_type_label = $entity_type->getBundleLabel()) {
      return $bundle_type_label;
    }
    if ($bundle_entity_type = $entity_type->getBundleEntityType()) {
      return $this->entityTypeManager->getDefinition($bundle_entity_type)->getLabel();
    }
    return $this->t('@label type', ['@label' => $entity_type->getLabel()]);
  }

}
