<?php

namespace Drupal\r4032login\Form;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Path\PathValidatorInterface;
use Drupal\Core\Url;
use Drupal\user\Entity\User;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure r4032login settings for this site.
 */
class AnonymousSettingsForm extends ConfigFormBase {

  /**
   * The path validator service.
   *
   * @var \Drupal\Core\Path\PathValidatorInterface
   */
  protected $pathValidator;

  /**
   * Constructs an AnonymousSettingsForm.
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
    return 'r4032login_anonymous_settings_form';
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

    $form['user_login_path'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Redirect anonymous users to'),
      '#description' => $this->t('If anonymous user tries to access a page they can not, redirect them to the given page. Include the leading slash, i.e.: /user/login.'),
      '#default_value' => $config->get('user_login_path'),
    ];
    $form['redirect_to_destination'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Redirect anonymous users to the page they tried to access after login'),
      '#default_value' => $config->get('redirect_to_destination'),
    ];
    $form['destination_parameter_override'] = [
      '#type' => 'textfield',
      '#title' => $this->t("Destination parameter override"),
      '#description' => $this->t("The parameter to use when setting the return destination once login has succeeded. By default Drupal uses 'destination', but overriding this may be necessary if using an external login system such as CAS, Shibboleth or OAuth."),
      '#default_value' => $config->get('destination_parameter_override'),
    ];
    $form['display_denied_message'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Display access denied message on login page'),
      '#description' => $this->t('Displays an access denied message on the user login page.'),
      '#default_value' => $config->get('display_denied_message'),
    ];
    $form['access_denied_message'] = [
      '#type' => 'textarea',
      '#rows' => 1,
      '#title' => $this->t("User login 'access denied' message"),
      '#description' => $this->t('The message text displayed to anonymous users who are denied access to the page.'),
      '#default_value' => $config->get('access_denied_message'),
      '#states' => [
        'invisible' => [
          'input[name="display_denied_message"]' => [
            'checked' => FALSE,
          ],
        ],
      ],
    ];
    $form['access_denied_message_type'] = [
      '#type' => 'select',
      '#title' => $this->t("User login 'access denied' message type"),
      '#description' => $this->t('The message type displayed to users who are denied access to the page.'),
      '#default_value' => $config->get('access_denied_message_type'),
      '#options' => [
        'error' => $this->t('Error'),
        'warning' => $this->t('Warning'),
        'status' => $this->t('Status'),
      ],
      '#states' => [
        'invisible' => [
          'input[name="display_denied_message"]' => [
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
    if (!$form_state->isValueEmpty('user_login_path')) {
      $r4032loginUserLoginPath = $form_state->getValue('user_login_path');

      // Check the path validity
      // and whether the anonymous user can access the entered path.
      if (!UrlHelper::isExternal($r4032loginUserLoginPath)
        && (($r4032loginUserLoginPath != '<front>') || ($r4032loginUserLoginPath = Url::fromRoute($r4032loginUserLoginPath)->toString()))
        && (!$this->pathValidator->getUrlIfValidWithoutAccessCheck($r4032loginUserLoginPath)
          || !($url = Url::fromUserInput($r4032loginUserLoginPath))
          || !$url->access(User::getAnonymousUser()))
      ) {
        $form_state->setErrorByName('user_login_path', $this->t("The user login form path '%path' is either invalid or a logged out user does not have access to it.", [
          '%path' => $form_state->getValue('user_login_path'),
        ]));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('r4032login.settings')
      ->set('user_login_path', $form_state->getValue('user_login_path'))
      ->set('redirect_to_destination', $form_state->getValue('redirect_to_destination'))
      ->set('destination_parameter_override', $form_state->getValue('destination_parameter_override'))
      ->set('display_denied_message', $form_state->getValue('display_denied_message'))
      ->set('access_denied_message', $form_state->getValue('access_denied_message'))
      ->set('access_denied_message_type', $form_state->getValue('access_denied_message_type'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
