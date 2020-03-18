<?php

namespace Drupal\addtoany\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Extension\ModuleHandler;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure AddToAny settings for this site.
 */
class AddToAnySettingsForm extends ConfigFormBase {
  /**
   * Drupal\Core\Extension\ModuleHandler definition.
   *
   * @var Drupal\Core\Extension\ModuleHandler
   */
  protected $moduleHandler;

  /**
   * Constructs a AddToAnySettingsForm object.
   *
   * @param \Drupal\Core\Extension\ModuleHandler $module_handler
   *   The factory for configuration objects.
   */
  public function __construct(ModuleHandler $module_handler) {
    $this->moduleHandler = $module_handler;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('module_handler')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'addtoany_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'addtoany.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    global $base_path;

    $addtoany_settings = $this->config('addtoany.settings');

    $button_img = '<img src="' . $base_path . drupal_get_path('module', 'addtoany') . '/images/%s" width="%d" height="%d"%s />';

    $button_options = [
      'default' => sprintf($button_img, 'a2a_32_32.svg', 32, 32, ' class="addtoany-round-icon"'),
      'custom' => $this->t('Custom button'),
      'none' => $this->t('None'),
    ];

    $attributes_for_code = [
      'autocapitalize' => ['off'],
      'autocomplete' => ['off'],
      'autocorrect' => ['off'],
      'spellcheck' => ['false'],
    ];

    // Attach CSS and JS.
    $form['#attached']['library'][] = 'addtoany/addtoany.admin';

    $form['addtoany_button_settings'] = [
      '#type'         => 'details',
      '#title'        => $this->t('Buttons'),
      '#open'         => TRUE,
    ];
    $form['addtoany_button_settings']['addtoany_buttons_size'] = [
      '#type'          => 'number',
      '#title'         => $this->t('Icon size'),
      '#field_suffix'  => ' ' . $this->t('pixels'),
      '#default_value' => $addtoany_settings->get('buttons_size'),
      '#size'          => 10,
      '#maxlength'     => 3,
      '#min'           => 8,
      '#max'           => 999,
      '#required'      => TRUE,
    ];
    $form['addtoany_button_settings']['addtoany_service_button_settings'] = [
      '#type'         => 'details',
      '#title'        => $this->t('Service Buttons'),
      '#collapsible'  => TRUE,
      '#collapsed'    => TRUE,
    ];
    $form['addtoany_button_settings']['addtoany_service_button_settings']['addtoany_additional_html'] = [
      '#type'          => 'textarea',
      '#title'         => $this->t('Service Buttons HTML code'),
      '#default_value' => $addtoany_settings->get('additional_html'),
      '#description'   => $this->t('You can add HTML code to display customized <a href="https://www.addtoany.com/buttons/customize/drupal/standalone_services" target="_blank">standalone service buttons</a> next to each universal share button. For example: <br /> <code>&lt;a class=&quot;a2a_button_facebook&quot;&gt;&lt;/a&gt;<br />&lt;a class=&quot;a2a_button_twitter&quot;&gt;&lt;/a&gt;<br />&lt;a class=&quot;a2a_button_pinterest&quot;&gt;&lt;/a&gt;</code>
      '),
      '#attributes' => $attributes_for_code,
    ];
    $form['addtoany_button_settings']['universal_button'] = [
      '#type'         => 'details',
      '#title'        => $this->t('Universal Button'),
      '#collapsible'  => TRUE,
      '#collapsed'    => TRUE,
      /* #states workaround in addtoany.admin.js */
    ];
    $form['addtoany_button_settings']['universal_button']['addtoany_universal_button'] = [
      '#type'          => 'radios',
      '#title'         => $this->t('Button'),
      '#default_value' => $addtoany_settings->get('universal_button'),
      '#attributes'    => ['class' => ['addtoany-universal-button-option']],
      '#options'       => $button_options,
    ];
    $form['addtoany_button_settings']['universal_button']['addtoany_custom_universal_button'] = [
      '#type'          => 'textfield',
      '#title'         => $this->t('Custom button URL'),
      '#default_value' => $addtoany_settings->get('custom_universal_button'),
      '#description'   => $this->t('URL of the button image. Example: http://example.com/share.png'),
      '#states'        => [
        // Show only if custom button is selected.
        'visible' => [
          ':input[name="addtoany_universal_button"]' => ['value' => 'custom'],
        ],
      ],
    ];
    $form['addtoany_button_settings']['universal_button']['addtoany_universal_button_placement'] = [
      '#type'          => 'radios',
      '#title'         => $this->t('Button placement'),
      '#default_value' => $addtoany_settings->get('universal_button_placement'),
      '#options'       => [
        'after' => $this->t('After the service buttons'),
        'before' => $this->t('Before the service buttons'),
      ],
      '#states'        => [
        // Hide when universal sharing is disabled.
        'invisible' => [
          ':input[name="addtoany_universal_button"]' => ['value' => 'none'],
        ],
      ],
    ];

    $form['addtoany_additional_settings'] = [
      '#type'         => 'details',
      '#title'        => $this->t('Additional options'),
      '#collapsible'  => TRUE,
      '#collapsed'    => TRUE,
    ];
    $form['addtoany_additional_settings']['addtoany_additional_js'] = [
      '#type'          => 'textarea',
      '#title'         => $this->t('Additional JavaScript'),
      '#default_value' => $addtoany_settings->get('additional_js'),
      '#description'   => $this->t('You can add special JavaScript code for AddToAny. See <a href="https://www.addtoany.com/buttons/customize/drupal" target="_blank">AddToAny documentation</a>.'),
      '#attributes' => $attributes_for_code,
    ];
    $form['addtoany_additional_settings']['addtoany_additional_css'] = [
      '#type'          => 'textarea',
      '#title'         => $this->t('Additional CSS'),
      '#default_value' => $addtoany_settings->get('additional_css'),
      '#description'   => $this->t('You can add special CSS code for AddToAny. See <a href="https://www.addtoany.com/buttons/customize/drupal" target="_blank">AddToAny documentation</a>.'),
      '#attributes' => $attributes_for_code,
    ];
    $form['addtoany_additional_settings']['addtoany_no_3p'] = [
      '#type'          => 'checkbox',
      '#title'         => $this->t('Disable 3rd party cookies'),
      '#default_value' => $addtoany_settings->get('no_3p'),
      '#description'   => $this->t('Disabling may affect analytics and limit some functionality.'),
    ];

    if ($this->moduleHandler->moduleExists('token')) {
      $form['tokens'] = [
        '#theme' => 'token_tree_link',
        '#token_types' => ['node'],
        '#global_types' => TRUE,
        '#click_insert' => TRUE,
        '#show_restricted' => FALSE,
        '#recursion_limit' => 3,
        '#text' => $this->t('Browse available tokens'),
      ];
    }

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    $this->config('addtoany.settings')
      ->set('additional_css', $values['addtoany_additional_css'])
      ->set('additional_html', $values['addtoany_additional_html'])
      ->set('additional_js', $values['addtoany_additional_js'])
      ->set('buttons_size', $values['addtoany_buttons_size'])
      ->set('custom_universal_button', $values['addtoany_custom_universal_button'])
      ->set('universal_button', $values['addtoany_universal_button'])
      ->set('universal_button_placement', $values['addtoany_universal_button_placement'])
      ->set('no_3p', $values['addtoany_no_3p'])
      ->save();

    parent::submitForm($form, $form_state);
  }

}
