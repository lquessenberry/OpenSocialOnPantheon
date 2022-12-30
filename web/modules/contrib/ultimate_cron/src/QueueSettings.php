<?php

namespace Drupal\ultimate_cron;

use Drupal\Core\Logger\RfcLogLevel;
use Drupal\ultimate_cron\Entity\CronJob;
use Drupal\ultimate_cron\TaggedSettings;

/**
 * Queue settings plugin class.
 */
class QueueSettings extends TaggedSettings {
  static private $throttled = array();
  static private $queues = NULL;

  /**
   * Get cron queues and static cache them.
   *
   * Works like module_invoke_all('cron_queue_info'), but adds
   * a 'module' to each item.
   *
   * @return array
   *   Cron queue definitions.
   */
  private function get_queues() {
    if (!isset(self::$queues)) {
      $queues = array();
      foreach (module_implements('cron_queue_info') as $module) {
        $items = module_invoke($module, 'cron_queue_info');
        if (is_array($items)) {
          foreach ($items as &$item) {
            $item['module'] = $module;
          }
          $queues += $items;
        }
      }
      drupal_alter('cron_queue_info', $queues);
      self::$queues = $queues;
    }
    return $queues;
  }

  /**
   * Implements hook_cronapi().
   */
  public function cronapi() {
    $items = array();
    if (!variable_get($this->key . '_enabled', TRUE)) {
      return $items;
    }

    // Grab the defined cron queues.
    $queues = self::get_queues();

    foreach ($queues as $name => $info) {
      if (!empty($info['skip on cron'])) {
        continue;
      }

      $items['queue_' . $name] = array(
        'title' => t('Queue: @name', array('@name' => $name)),
        'callback' => array(get_class($this), 'worker_callback'),
        'scheduler' => array(
          'simple' => array(
            'rules' => array('* * * * *'),
          ),
          'crontab' => array(
            'rules' => array('* * * * *'),
          ),
        ),
        'settings' => array(
          'queue' => array(
            'name' => $name,
            'worker callback' => $info['worker callback'],
          ),
        ),
        'tags' => array('queue', 'core', 'killable'),
        'module' => $info['module'],
      );
      if (isset($info['time'])) {
        $items['queue_' . $name]['settings']['queue']['time'] = $info['time'];
      }
    }

    return $items;
  }

  /**
   * Process a cron queue.
   *
   * This is a wrapper around the cron queues "worker callback".
   *
   * @param CronJob $job
   *   The job being run.
   */
  static public function worker_callback($job) {
    $settings = $job->getPluginSettings('settings');
    $queue = DrupalQueue::get($settings['queue']['name']);
    $function = $settings['queue']['worker callback'];

    $end = microtime(TRUE) + $settings['queue']['time'];
    $items = 0;
    while (microtime(TRUE) < $end) {
      if ($job->getSignal('kill')) {
        \Drupal::logger('ultimate_cron')->warning('kill signal recieved');
        break;
      }

      $item = $queue->claimItem($settings['queue']['lease_time']);
      if (!$item) {
        if ($settings['queue']['empty_delay']) {
          usleep($settings['queue']['empty_delay'] * 1000000);
          continue;
        }
        else {
          break;
        }
      }
      try {
        if ($settings['queue']['item_delay']) {
          if ($items == 0) {
            // Move the boundary if using a throttle, to avoid waiting for nothing.
            $end -= $settings['queue']['item_delay'] * 1000000;
          }
          else {
            // Sleep before retrieving.
            usleep($settings['queue']['item_delay'] * 1000000);
          }
        }
        $function($item->data);
        $queue->deleteItem($item);
        $items++;
      }
      catch (Exception $e) {
        // Just continue ...
        \Drupal::logger($job->hook['module'])->error("Queue item @item_id from queue @queue failed with message @message", array(
          '@item_id' => $item->item_id,
          '@queue' => $settings['queue']['name'],
          '@message' => $e->getMessage()
        ));
      }
    }
    \Drupal::logger($job->hook['module'])->info('Processed @items items from queue @queue', array(
      '@items' => $items,
      '@queue' => $settings['queue']['name'],
    ));

    // Re-throttle.
    $job->getPlugin('settings', 'queue')->throttle($job);

    return;
  }

  /**
   * Implements hook_cron_alter().
   */
  public function cron_alter(&$jobs) {
    $new_jobs = array();
    foreach ($jobs as $job) {
      if (!$this->isValid($job)) {
        continue;
      }
      $settings = $job->getSettings();
      if (isset($settings['settings']['queue']['name'])) {
        if ($settings['settings']['queue']['throttle']) {
          for ($i = 2; $i <= $settings['settings']['queue']['threads']; $i++) {
            $name = $job->id() . '_' . $i;
            $hook = $job->hook;
            $hook['settings']['queue']['master'] = $job->id();
            $hook['settings']['queue']['thread'] = $i;
            $hook['name'] = $name;
            $hook['title'] .= " (#$i)";
            $hook['immutable'] = TRUE;
            $new_jobs[$name] = ultimate_cron_prepare_job($name, $hook);
            $new_jobs[$name]->settings = $settings + $new_jobs[$name]->settings;
            $new_jobs[$name]->title = $job->title . " (#$i)";
          }
        }
      }
    }
    $jobs += $new_jobs;
  }

  /**
   * Implements hook_cron_alter().
   */
  public function cron_pre_schedule($job) {
    $queue_name = !empty($job->hook['settings']['queue']['name']) ? $job->hook['settings']['queue']['name'] : FALSE;
    if ($queue_name) {
      if (empty(self::$throttled[$job->id()])) {
        self::$throttled[$job->id()] = TRUE;
        $this->throttle($job);
      }
    }
  }

  /**
   * Default settings.
   */
  public function defaultSettings() {
    return array(
      'lease_time' => 30,
      'empty_delay' => 0,
      'item_delay' => 0,
      'throttle' => FALSE,
      'threads' => 4,
      'threshold' => 10,
      'time' => 15,
    );
  }

  /**
   * Settings form.
   */
  public function settingsForm(&$form, &$form_state, $job = NULL) {
    $elements = &$form['settings'][$this->type][$this->name];
    $values = &$form_state['values']['settings'][$this->type][$this->name];

    $states = array();
    if (!$job) {
      $elements['enabled'] = array(
        '#title' => t('Enable cron queue processing'),
        '#description' => t('If enabled, cron queues will be processed by this plugin. If another cron queue plugin is installed, it may be necessary/beneficial to disable this plugin.'),
        '#type' => 'checkbox',
        '#default_value' => variable_get($this->key . '_enabled', TRUE),
        '#fallback' => TRUE,
      );
      $states = array(
        '#states' => array(
          'visible' => array(
            ':input[name="settings[' . $this->type . '][' . $this->name . '][enabled]"]' => array(
              'checked' => TRUE,
            ),
          ),
        ),
      );
    }

    $elements['timeouts'] = array(
      '#type' => 'fieldset',
      '#title' => t('Timeouts'),
    ) + $states;
    $elements['timeouts']['lease_time'] = array(
      '#parents' => array('settings', $this->type, $this->name, 'lease_time'),
      '#title' => t("Queue lease time"),
      '#type' => 'textfield',
      '#default_value' => $values['lease_time'],
      '#description' => t('Seconds to claim a cron queue item.'),
      '#fallback' => TRUE,
      '#required' => TRUE,
    );
    $elements['timeouts']['time'] = array(
      '#parents' => array('settings', $this->type, $this->name, 'time'),
      '#title' => t('Time'),
      '#type' => 'textfield',
      '#default_value' => $values['time'],
      '#description' => t('Time in seconds to process items during a cron run.'),
      '#fallback' => TRUE,
      '#required' => TRUE,
    );

    $elements['delays'] = array(
      '#type' => 'fieldset',
      '#title' => t('Delays'),
    ) + $states;
    $elements['delays']['empty_delay'] = array(
      '#parents' => array('settings', $this->type, $this->name, 'empty_delay'),
      '#title' => t("Empty delay"),
      '#type' => 'textfield',
      '#default_value' => $values['empty_delay'],
      '#description' => t('Seconds to delay processing of queue if queue is empty (0 = end job).'),
      '#fallback' => TRUE,
      '#required' => TRUE,
    );
    $elements['delays']['item_delay'] = array(
      '#parents' => array('settings', $this->type, $this->name, 'item_delay'),
      '#title' => t("Item delay"),
      '#type' => 'textfield',
      '#default_value' => $values['item_delay'],
      '#description' => t('Seconds to wait between processing each item in a queue.'),
      '#fallback' => TRUE,
      '#required' => TRUE,
    );

    $elements['throttle'] = array(
      '#title' => t('Throttle'),
      '#type' => 'checkbox',
      '#default_value' => $values['throttle'],
      '#description' => t('Throttle queues using multiple threads.'),
    );

    $states = !$job ? $states : array(
      '#states' => array(
        'visible' => array(':input[name="settings[' . $this->type . '][' . $this->name . '][throttle]"]' => array('checked' => TRUE))
      ),
    );

    $elements['throttling'] = array(
      '#type' => 'fieldset',
      '#title' => t('Throttling'),
    ) + $states;
    $elements['throttling']['threads'] = array(
      '#parents' => array('settings', $this->type, $this->name, 'threads'),
      '#title' => t('Threads'),
      '#type' => 'textfield',
      '#default_value' => $values['threads'],
      '#description' => t('Number of threads to use for queues.'),
      '#states' => array(
        'visible' => array(':input[name="settings[' . $this->type . '][' . $this->name . '][throttle]"]' => array('checked' => TRUE))
      ),
      '#fallback' => TRUE,
      '#required' => TRUE,
    );
    $elements['throttling']['threshold'] = array(
      '#parents' => array('settings', $this->type, $this->name, 'threshold'),
      '#title' => t('Threshold'),
      '#type' => 'textfield',
      '#default_value' => $values['threshold'],
      '#description' => t('Number of items in queue required to activate the next cron job.'),
      '#states' => array(
        'visible' => array(':input[name="settings[' . $this->type . '][' . $this->name . '][throttle]"]' => array('checked' => TRUE))
      ),
      '#fallback' => TRUE,
      '#required' => TRUE,
    );
  }

  /**
   * Form submit handler.
   */
  public function settingsFormSubmit(&$form, &$form_state, $job = NULL) {
    if (!$job) {
      $values = &$form_state['values']['settings'][$this->type][$this->name];
      variable_set($this->key . '_enabled', $values['enabled']);
      unset($values['enabled']);
    }
  }

  /**
   * Throttle queues.
   *
   * Enables or disables queue threads depending on remaining items in queue.
   */
  public function throttle($job) {
    if (!empty($job->hook['settings']['queue']['master'])) {
      // We always base the threads on the master.
      $master_job = ultimate_cron_job_load($job->hook['settings']['queue']['master']);
      $settings = $master_job->getSettings('settings');
    }
    else {
      return;
    }
    if ($settings['queue']['throttle']) {
      $queue = DrupalQueue::get($settings['queue']['name']);
      $items = $queue->numberOfItems();
      $thread = $job->hook['settings']['queue']['thread'];

      $name = $master_job->name . '_' . $thread;
      $status = empty($master_job->disabled) && ($items >= ($thread - 1) * $settings['queue']['threshold']);
      $new_status = !$status ? TRUE : FALSE;
      $old_status = ultimate_cron_job_get_status($name) ? TRUE : FALSE;
      if ($old_status !== $new_status) {
        $log_entry = $job->startLog(uniqid($job->id(), TRUE), 'throttling', ULTIMATE_CRON_LOG_TYPE_ADMIN);
        $log_entry->log('Job @status by queue throttling (items:@items, boundary:@boundary, threshold:@threshold)', array(
          '@status' => $new_status ? t('disabled') : t('enabled'),
          '@items' => $items,
          '@boundary' => ($thread - 1) * $settings['queue']['threshold'],
          '@threshold' => $settings['queue']['threshold'],
        ), RfcLogLevel::INFO);
        $log_entry->finish();
        $job->dont_log = TRUE;
        ultimate_cron_job_set_status($job, $new_status);
        $job->disabled = $new_status;
      }
    }
  }
}
