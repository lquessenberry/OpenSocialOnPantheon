<?php

namespace Drupal\ultimate_cron\Form;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Datetime\DateFormatter;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\State\StateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form for general cron settings.
 */
class GeneralSettingsForm extends ConfigFormBase {

  /**
   * Stores the state storage service.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * The cron service.
   *
   * @var \Drupal\Core\CronInterface
   */
  protected $cron;

  /**
   * The date formatter service.
   *
   * @var \Drupal\Core\Datetime\DateFormatter
   */
  protected $dateFormatter;

  /**
   * Constructs a GeneralSettingsForm object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   * @param \Drupal\Core\State\StateInterface $state
   *   The state key value store.
   * @param \Drupal\Core\Datetime\DateFormatter $date_formatter
   *   The date formatter service.
   */
  public function __construct(ConfigFactoryInterface $config_factory, StateInterface $state, DateFormatter $date_formatter) {
    parent::__construct($config_factory);
    $this->state = $state;
    $this->dateFormatter = $date_formatter;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('state'),
      $container->get('date.formatter')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'ultimate_cron_general_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['ultimate_cron.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('ultimate_cron.settings');
    // Setup vertical tabs.
    $form['settings_tabs'] = [
      '#type' => 'vertical_tabs',
    ];

    // @todo enable this when supported again
    $form['nodejs'] = array(
      '#type' => 'checkbox',
      '#title' => t('nodejs'),
      '#default_value' => $config->get('nodejs'),
      '#description' => t('Enable nodejs integration (Live reload on jobs page. Requires the nodejs module to be installed and configured).'),
      '#fallback' => TRUE,

      '#access' => FALSE,
    );

    // Queue settings. Visual hierarchy disabled since this is currently
    // the only general settings group.
    $form['queue'] = [
      //'#type' => 'details',
      //'#title' => 'queue',
      //'#group' => 'settings_tabs',
      '#tree' => TRUE,
    ];

    $form['queue']['enabled'] = array(
      '#title' => t('Override cron queue processing'),
      '#description' => t('If enabled, queue workers are exposed as cron jobs and can be configured separately. When disabled, the standard queue processing is used. <strong>This feature is currently experimental, do not enable unless you need it.</strong>'),
      '#type' => 'checkbox',
      '#default_value' => $config->get('queue.enabled'),
      '#fallback' => TRUE,
    );

    $queue_states = array(
      '#states' => array(
        'visible' => array(':input[name="queue[enabled]"]' => array('checked' => TRUE)),
      ),
    );

    $form['queue']['timeouts'] = array(
      '#type' => 'fieldset',
      '#title' => t('Timeouts'),
    ) + $queue_states;
    $form['queue']['timeouts']['lease_time'] = array(
      '#title' => t("Queue lease time"),
      '#type' => 'number',
      '#default_value' => $config->get('queue.timeouts.lease_time'),
      '#description' => t('Seconds to claim a cron queue item.'),
      '#fallback' => TRUE,
      '#required' => TRUE,
      '#min' => 0,
      '#step' => 0.01
    );
    $form['queue']['timeouts']['time'] = array(
      '#title' => t('Time'),
      '#type' => 'number',
      '#default_value' => $config->get('queue.timeouts.time'),
      '#description' => t('Time in seconds to process items during a cron run.'),
      '#fallback' => TRUE,
      '#required' => TRUE,
      '#min' => 0,
      '#step' => 0.01
    );

    $form['queue']['delays'] = array(
      '#type' => 'fieldset',
      '#title' => t('Delays'),
    ) + $queue_states;
    $form['queue']['delays']['empty_delay'] = array(
      '#title' => t("Empty delay"),
      '#type' => 'number',
      '#default_value' => $config->get('queue.delays.empty_delay'),
      '#description' => t('Seconds to delay processing of queue if queue is empty (0 = end job).'),
      '#fallback' => TRUE,
      '#required' => TRUE,
      '#min' => 0,
      '#step' => 0.01
    );
    $form['queue']['delays']['item_delay'] = array(
      '#title' => t("Item delay"),
      '#type' => 'number',
      '#default_value' => $config->get('queue.delays.item_delay'),
      '#description' => t('Seconds to wait between processing each item in a queue.'),
      '#fallback' => TRUE,
      '#required' => TRUE,
      '#min' => 0,
      '#step' => 0.01
    );

    $throttle_states = array(
      '#states' => array(
        'visible' => array(':input[name="queue[throttle][enabled]"]' => array('checked' => TRUE)),
      ),
    );

    $form['queue']['throttle'] = array(
      '#type' => 'fieldset',
      '#title' => t('Throttling'),
      // @todo Show when throttling is implemented.
      '#access' => FALSE,
    ) + $queue_states;
    $form['queue']['throttle']['enabled'] = array(
      '#title' => t('Throttle'),
      '#type' => 'checkbox',
      '#default_value' => $config->get('queue.throttle.enabled'),
      '#description' => t('Throttle queues using multiple threads.'),
    );
    $form['queue']['throttle']['threads'] = array(
      '#title' => t('Threads'),
      '#type' => 'number',
      '#default_value' => $config->get('queue.throttle.threads'),
      '#description' => t('Number of threads to use for queues.'),
      '#fallback' => TRUE,
      '#required' => TRUE,
      '#min' => 0,
    ) + $throttle_states;
    $form['queue']['throttle']['threshold'] = array(
      '#title' => t('Threshold'),
      '#type' => 'number',
      '#default_value' => $config->get('queue.throttle.threshold'),
      '#description' => t('Number of items in queue required to activate the next cron job.'),
      '#fallback' => TRUE,
      '#required' => TRUE,
      '#min' => 0,
    ) + $throttle_states;

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('ultimate_cron.settings')
      ->set('queue', $form_state->getValue('queue'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
