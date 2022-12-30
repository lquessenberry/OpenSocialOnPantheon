<?php

namespace Drupal\r4032login\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Path\PathValidatorInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure r4032login authenticated settings for this site.
 */
class AuthenticatedSettingsForm extends ConfigFormBase {

  /**
   * The path validator service.
   *
   * @var \Drupal\Core\Path\PathValidatorInterface
   */
  protected $pathValidator;

  /**
   * Constructs a AuthenticatedSettingsForm.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   Defines the configuration object factory.
   * @param \Drupal\Core\Path\PathValidatorInterface $path_validator
   *   The path validator service.
   */
  public function __construct(ConfigFactoryInterface $config_factory, PathValidatorInterface $path_validator) {
    parent::__construct($config_factory);
    $this->pathValidator = $path_validator;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('path.validator')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'r4032login_authenticated_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['r4032login.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('r4032login.settings');

    $form['redirect_authenticated_users_to'] = [
      '#type' => 'textfield',
      '#title' => $this->t("Redirect authenticated users to"),
      '#description' => $this->t('If an authenticated user tries to access a page they can not, redirect them to the given page. Use &lt;front&gt; for the front page, leave blank for a default access denied page.'),
      '#default_value' => $config->get('redirect_authenticated_users_to'),
    ];
    $form['throw_authenticated_404'] = [
      '#type' => 'checkbox',
      '#title' => $this->t("Or throw authenticated users a 404 error"),
      '#description' => $this->t('If an authenticated user tries to access a page they can not, throws a 404 error (not found). Note that this will cancel the redirect option if set above.'),
      '#default_value' => $config->get('throw_authenticated_404'),
    ];

    $form['display_auth_denied_message'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Display access denied message on authenticated landing page'),
      '#default_value' => $config->get('display_auth_denied_message'),
    ];
    $form['access_denied_auth_message'] = [
      '#type' => 'textarea',
      '#rows' => 1,
      '#title' => $this->t("Authenticated user 'access denied' message"),
      '#description' => $this->t('The message text displayed to authenticated users who are denied access to the page.'),
      '#default_value' => $config->get('access_denied_auth_message'),
      '#states' => [
        'invisible' => [
          'input[name="display_auth_denied_message"]' => [
            'checked' => FALSE,
          ],
        ],
      ],
    ];
    $form['access_denied_auth_message_type'] = [
      '#type' => 'select',
      '#title' => $this->t("Authenticated user 'access denied' message type"),
      '#description' => $this->t('The message type displayed to authenticated users who are denied access to the page.'),
      '#default_value' => $config->get('access_denied_auth_message_type'),
      '#options' => [
        'error' => $this->t('Error'),
        'warning' => $this->t('Warning'),
        'status' => $this->t('Status'),
      ],
      '#states' => [
        'invisible' => [
          'input[name="display_auth_denied_message"]' => [
            'checked' => FALSE,
          ],
        ],
      ],
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    if (!$form_state->isValueEmpty('redirect_authenticated_users_to') && !$this->pathValidator->isValid($form_state->getValue(('redirect_authenticated_users_to')))) {
      $form_state->setErrorByName('redirect_authenticated_users_to', $this->t("The redirect authenticated users path '%path' is either invalid or you do not have access to it.", [
        '%path' => $form_state->getValue('redirect_authenticated_users_to'),
      ]));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('r4032login.settings')
      ->set('redirect_authenticated_users_to', $form_state->getValue('redirect_authenticated_users_to'))
      ->set('throw_authenticated_404', $form_state->getValue('throw_authenticated_404'))
      ->set('display_auth_denied_message', $form_state->getValue('display_auth_denied_message'))
      ->set('access_denied_auth_message', $form_state->getValue('access_denied_auth_message'))
      ->set('access_denied_auth_message_type', $form_state->getValue('access_denied_auth_message_type'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
