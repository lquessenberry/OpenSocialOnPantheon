<?php

/**
 * @file
 * Configures administrative settings for VotingAPI.
 */

namespace Drupal\votingapi\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Datetime\DateFormatter;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class SettingsForm.
 *
 * @package Drupal\votingapi\Form
 *
 * @ingroup votingapi
 */
class SettingsForm extends ConfigFormBase {

  /**
   * The date formatter service.
   *
   * @var \Drupal\Core\Datetime\DateFormatter
   */
  protected $dateFormatter;

  /**
   * Creates a CommentAdminOverview form.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   * @param \Drupal\Core\Datetime\DateFormatter $date_formatter
   *   The date formatter service.
   */
  public function __construct(ConfigFactoryInterface $config_factory, DateFormatter $date_formatter) {
    $this->setConfigFactory($config_factory);
    $this->dateFormatter = $date_formatter;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('date.formatter')
    );
  }

  /**
   * Returns a unique string identifying the form.
   *
   * @return string
   *   The unique string identifying the form.
   */
  public function getFormId() {
    return 'votingapi_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['votingapi.settings'];
  }

  /**
   * Defines the settings form for Vote entities.
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

    $config = $this->config('votingapi.settings');

    $unit_options = [
      300,
      900,
      1800,
      3600,
      10800,
      21600,
      32400,
      43200,
      86400,
      172800,
      345600,
      604800,
    ];

    $options = [];
    foreach ($unit_options as $option) {
      $options[$option] = $this->dateFormatter->formatInterval($option);;
    }

    $form['anonymous_window'] = [
      '#type' => 'select',
      '#title' => $this->t('Anonymous vote rollover'),
      '#description' => $this->t('The amount of time that must pass before two anonymous votes from the same computer are considered unique. Setting this to \'never\' will eliminate most double-voting, but will make it impossible for multiple anonymous on the same computer (like internet cafe customers) from casting votes.'),
      '#options' => $options,
      '#default_value' => $config->get('anonymous_window'),
    ];

    $form['user_window'] = [
      '#type' => 'select',
      '#title' => $this->t('Registered user vote rollover'),
      '#description' => $this->t('The amount of time that must pass before two registered user votes from the same user ID are considered unique. Setting this to \'never\' will eliminate most double-voting for registered users.'),
      '#options' => $options,
      '#default_value' => $config->get('user_window'),
    ];

    $form['calculation_schedule'] = [
      '#type' => 'radios',
      '#title' => $this->t('Calculation schedule'),
      '#description' => $this->t('On high-traffic sites, administrators can use this setting to postpone the calculation of vote results.'),
      '#default_value' => $config->get('calculation_schedule'),
      '#options' => [
        'immediate' => $this->t('Tally results whenever a vote is cast'),
        'cron' => $this->t('Tally results at cron-time'),
        'manual' => $this->t('Do not tally results automatically: I am using a module that manages its own vote results.'),
      ],
      '#required' => TRUE,
    ];

    $form['delete_everywhere'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Delete everywhere'),
      '#description' => $this->t('Allow deleting votes to someone else\'s entity when owner user of this votes is deleting.'),
      '#default_value' => $config->get('delete_everywhere'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('votingapi.settings');

    $settings = [
      'anonymous_window',
      'user_window',
      'calculation_schedule',
      'delete_everywhere',
    ];

    foreach ($settings as $setting) {
      $config->set($setting, $form_state->getValue($setting));
    }

    $config->save();

    parent::submitForm($form, $form_state);
  }
}
