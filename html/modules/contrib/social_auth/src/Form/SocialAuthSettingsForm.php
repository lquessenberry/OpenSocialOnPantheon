<?php

namespace Drupal\social_auth\Form;

use Drupal\Component\Utility\Html;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\RouteProviderInterface;
use Drupal\Core\Path\PathValidatorInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines a form that configures Social Auth settings.
 */
class SocialAuthSettingsForm extends ConfigFormBase {
  /**
   * The route provider.
   *
   * @var \Drupal\Core\Routing\RouteProviderInterface
   */
  protected $routeProvider;

  /**
   * The path validator.
   *
   * @var \Drupal\Core\Path\PathValidatorInterface
   */
  protected $pathValidator;

  /**
   * Constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The configuration factory.
   * @param \Drupal\Core\Routing\RouteProviderInterface $route_provider
   *   Used to check if route exists.
   * @param \Drupal\Core\Path\PathValidatorInterface $path_validator
   *   Used to check if path is valid and exists.
   */
  public function __construct(ConfigFactoryInterface $config_factory, RouteProviderInterface $route_provider, PathValidatorInterface $path_validator) {
    parent::__construct($config_factory);
    $this->routeProvider = $route_provider;
    $this->pathValidator = $path_validator;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('router.route_provider'),
      $container->get('path.validator')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'social_auth_admin_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'social_auth.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $social_auth_config = $this->config('social_auth.settings');

    $form['social_auth'] = array(
      '#type' => 'details',
      '#title' => $this->t('Social Auth Settings'),
      '#open' => TRUE,
      '#description' => $this->t('These settings allow you to configure how Social Auth module behaves on your Drupal site'),
    );

    $form['social_auth']['post_login'] = array(
      '#type' => 'textfield',
      '#required' => TRUE,
      '#title' => $this->t('Post login path or route'),
      '#description' => $this->t('Drupal path or route where the user should be redirected after successful login. If path, it must begin with <em>/, #</em> or <em>?</em>. Use <em>&lt;front&gt;</em> to redirect to front page.'),
      '#default_value' => $social_auth_config->get('post_login'),
    );

    $form['social_auth']['redirect_user_form'] = array(
      '#type' => 'checkbox',
      '#title' => $this->t('Redirect new users to Drupal user form'),
      '#description' => $this->t('If you check this, new users are redirected to Drupal user form after the user is created. This is useful if you want to encourage users to fill in additional user fields.'),
      '#default_value' => $social_auth_config->get('redirect_user_form'),
    );

    $form['social_auth']['disable_admin_login'] = array(
      '#type' => 'checkbox',
      '#title' => $this->t('Disable Social Auth login for administrator'),
      '#description' => $this->t('Disabling Social Auth login for administrator (<em>user 1</em>) can help protect your site if a security vulnerability is ever discovered in some Social Network PHP SDK or this module.'),
      '#default_value' => $social_auth_config->get('disable_admin_login'),
    );

    // Option to disable Social Auth for specific roles.
    $roles = user_roles();
    $options = array();
    foreach ($roles as $key => $role_object) {
      if ($key != 'anonymous' && $key != 'authenticated') {
        $options[$key] = Html::escape($role_object->get('label'));
      }
    }

    $form['social_auth']['disabled_roles'] = array(
      '#type' => 'checkboxes',
      '#title' => $this->t('Disable Social Auth login for the following roles'),
      '#options' => $options,
      '#default_value' => $social_auth_config->get('disabled_roles'),
    );
    if (empty($roles)) {
      $form['social_auth']['disabled_roles']['#description'] = $this->t('No roles found.');
    }

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    $post_login = $values['post_login'];
    $routes = $this->routeProvider
      ->getRoutesByNames(array($post_login));

    if (empty($routes)) {
      // If it is not a route and value does not start with '/', '#', or '?'.
      if (!in_array($post_login[0], array("/", "#", "?"))) {
        $form_state->setErrorByName('post_login', $this->t("The route doesn't exist. If path, it must begin with <em>/, #</em> or <em>?</em>"));
      }
      // If path is valid, check if it exists.
      if (!$this->pathValidator->getUrlIfValidWithoutAccessCheck($post_login)) {
        $form_state->setErrorByName('post_login', $this->t("The path or route you entered is not valid."));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    $this->config('social_auth.settings')
      ->set('post_login', $values['post_login'])
      ->set('redirect_user_form', $values['redirect_user_form'])
      ->set('disable_admin_login', $values['disable_admin_login'])
      ->set('disabled_roles', $values['disabled_roles'])
      ->save();

    parent::submitForm($form, $form_state);
  }

}
