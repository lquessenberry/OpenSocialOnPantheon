<?php

namespace Drupal\ultimate_cron\Plugin\ultimate_cron\Launcher;

use Drupal\Core\Form\FormStateInterface;
use Drupal\ultimate_cron\CronJobInterface;
use Drupal\ultimate_cron\Launcher\LauncherBase;
use Drupal\ultimate_cron\PluginCleanupInterface;

/**
 * Ultimate Cron launcher plugin class.
 *
 * @LauncherPlugin(
 *   id = "serial",
 *   title = @Translation("Serial"),
 *   description = @Translation("Launches scheduled jobs in the same thread and runs them consecutively."),
 * )
 */
class SerialLauncher extends LauncherBase implements PluginCleanupInterface {

  public $currentThread = NULL;

  /**
   * Implements hook_cron_alter().
   */
  public function cron_alter(&$jobs) {
    $lock = \Drupal::service('ultimate_cron.lock');
    if (!empty($lock->{$killable})) {
      $jobs['ultimate_cron_plugin_launcher_serial_cleanup']->hook['tags'][] = 'killable';
    }
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return array(
      'timeouts' => array(
        'lock_timeout' => 3600,
        'max_execution_time' => 3600,
      ),
      'launcher' => array(
        'max_threads' => 1,
        'thread' => 'any',
      ),
    ) + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form['timeouts'] = array(
      '#type' => 'fieldset',
      '#title' => t('Timeouts'),
    );

    $form['launcher'] = array(
      '#type' => 'fieldset',
      '#title' => t('Launching options'),
    );

    $form['timeouts']['lock_timeout'] = array(
      '#title' => t("Job lock timeout"),
      '#type' => 'textfield',
      '#default_value' => $this->configuration['timeouts']['lock_timeout'],
      '#description' => t('Number of seconds to keep lock on job.'),
      '#fallback' => TRUE,
      '#required' => TRUE,
    );

    // @todo: Figure this out when converting global settings to use plugin
    // classes.
    if (FALSE) {
      $form['timeouts']['max_execution_time'] = array(
        '#title' => t("Maximum execution time"),
        '#type' => 'textfield',
        '#default_value' => $this->configuration['timeouts']['max_execution_time'],
        '#description' => t('Maximum execution time for a cron run in seconds.'),
        '#fallback' => TRUE,
        '#required' => TRUE,
      );
      $form['launcher']['max_threads'] = array(
        '#title' => t("Maximum number of launcher threads"),
        '#type' => 'number',
        '#default_value' => $this->configuration['launcher']['max_threads'],
        '#description' => t('The maximum number of launch threads that can be running at any given time.'),
        '#fallback' => TRUE,
        '#required' => TRUE,
        '#weight' => 1,
      );

      return $form;
    }
    else {
      $max_threads = isset($this->configuration['launcher']['max_threads']) ? $this->configuration['launcher']['max_threads'] : 1;
    }

    $options = array(
      'any' => t('-- Any -- '),
      'fixed' => t('-- Fixed -- '),
    );
    for ($i = 1; $i <= $max_threads; $i++) {
      $options[$i] = $i;
    }


    $form['launcher']['thread'] = array(
      '#title' => t("Run in thread"),
      '#type' => 'select',
      '#default_value' => isset($this->configuration['launcher']['thread']) ? $this->configuration['launcher']['thread'] : 'any',
      '#options' => $options,
      '#description' => t('Which thread to run in when invoking with ?thread=N. Note: This setting only has an effect when cron is run through cron.php with an argument ?thread=N or through Drush with --options=thread=N.'),
      '#fallback' => TRUE,
      '#required' => TRUE,
      '#weight' => 2,
    );
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsFormValidate(&$form, &$form_state, $job = NULL) {
    $elements = & $form['configuration'][$this->type][$this->name];
    $values = & $form_state['values']['configuration'][$this->type][$this->name];
    if (!$job) {
      if (intval($values['max_threads']) <= 0) {
        form_set_error("settings[$this->type][$this->name", t('%title must be greater than 0', array(
          '%title' => $elements['launcher']['max_threads']['#title']
        )));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function lock(CronJobInterface $job) {
    if (array_key_exists('timeouts', $this->configuration)) {
      $timeout = $this->configuration['timeouts']['lock_timeout'];
    }
    else {
      $timeout = 0;
    }

    $lock = \Drupal::service('ultimate_cron.lock');
    if ($lock_id = $lock->lock($job->id(), $timeout)) {
      $lock_id = $this->getPluginId() . '-' . $lock_id;
      return $lock_id;
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function unlock($lock_id, $manual = FALSE) {
    list($launcher, $lock_id) = explode('-', $lock_id, 2);
    $lock = \Drupal::service('ultimate_cron.lock');
    return $lock->unlock($lock_id);
  }

  /**
   * {@inheritdoc}
   */
  public function isLocked(CronJobInterface $job) {
    $lock = \Drupal::service('ultimate_cron.lock');
    $lock_id = $lock->isLocked($job->id());
    return $lock_id ? $this->pluginId . '-' . $lock_id : $lock_id;
  }

  /**
   * {@inheritdoc}
   */
  public function isLockedMultiple(array $jobs) {
    $names = array();
    foreach ($jobs as $job) {
      $names[] = $job->id();
    }
    $lock = \Drupal::service('ultimate_cron.lock');
    $lock_ids = $lock->isLockedMultiple($names);
    foreach ($lock_ids as &$lock_id) {
      $lock_id = $lock_id ? $this->pluginId . '-' . $lock_id : $lock_id;
    }
    return $lock_ids;
  }

  /**
   * {@inheritdoc}
   */
  public function cleanup() {
    $lock = \Drupal::service('ultimate_cron.lock');
    $lock->cleanup();
  }

  /**
   * {@inheritdoc}
   */
  public function launch(CronJobInterface $job) {
    \Drupal::moduleHandler()->invokeAll('cron_pre_launch', array($this));

    if ($this->currentThread) {
      $init_message = t('Launched in thread @current_thread', array(
        '@current_thread' => $this->currentThread,
      ));
    }
    else {
      $init_message = t('Launched manually');
    }

    // Run job.
    $job_launch = $job->run($init_message);
    \Drupal::moduleHandler()->invokeAll('cron_post_launch', array($this));

    return $job_launch;
  }

  /**
   * {@inheritdoc}
   */
  public function findFreeThread($lock, $lock_timeout = NULL, $timeout = 3) {
    $configuration = $this->getConfiguration();

    // Find a free thread, try for 3 seconds.
    $delay = $timeout * 1000000;
    $sleep = 25000;

    $lock_service = \Drupal::service('ultimate_cron.lock');
    do {
      for ($thread = 1; $thread <= $configuration['launcher']['max_threads']; $thread++) {
        if ($thread != $this->currentThread) {
          $lock_name = 'ultimate_cron_serial_launcher_' . $thread;
          if (!$lock_service->isLocked($lock_name)) {
            if ($lock) {
              if ($lock_id = $lock_service->lock($lock_name, $lock_timeout)) {
                return array($thread, $lock_id);
              }
            }
            else {
              return array($thread, FALSE);
            }
          }
        }
      }
      if ($delay > 0) {
        usleep($sleep);
        // After each sleep, increase the value of $sleep until it reaches
        // 500ms, to reduce the potential for a lock stampede.
        $delay = $delay - $sleep;
        $sleep = min(500000, $sleep + 25000, $delay);
      }
    } while ($delay > 0);
    return array(FALSE, FALSE);
  }

  /**
   * {@inheritdoc}
   */
  public function launchJobs(array $jobs) {
    $lock = \Drupal::service('ultimate_cron.lock');
    $configuration = $this->getConfiguration();

    // Set proper max execution time.
    $max_execution_time = ini_get('max_execution_time');
    $lock_timeout = max($max_execution_time, $configuration['timeouts']['max_execution_time']);

    // We only lock for 55 seconds at a time, to give room for other cron
    // runs.
    // @todo: Why hard-code this?
    $lock_timeout = 55;

    if (!empty($_GET['thread'])) {
      self::setGlobalOption('thread', $_GET['thread']);
    }

    if ($thread = intval(self::getGlobalOption('thread'))) {
      if ($thread < 1 || $thread > $configuration['launcher']['max_threads']) {
        \Drupal::logger('serial_launcher')->warning("Invalid thread available for starting launch thread");
        return;
      }

      $lock_name = 'ultimate_cron_serial_launcher_' . $thread;
      $lock_id = NULL;
      if (!$lock->isLocked($lock_name)) {
        $lock_id = $lock->lock($lock_name, $lock_timeout);
      }
      if (!$lock_id) {
        \Drupal::logger('serial_launcher')->warning("Thread @thread is already running", array(
          '@thread' => $thread,
        ));
      }
    }
    else {
      $timeout = 1;
      list($thread, $lock_id) = $this->findFreeThread(TRUE, $lock_timeout, $timeout);
    }
    $this->currentThread = $thread;

    if (!$thread) {
      \Drupal::logger('serial_launcher')->warning("No free threads available for launching jobs");
      return;
    }

    if ($max_execution_time && $max_execution_time < $configuration['timeouts']['max_execution_time']) {
      set_time_limit($configuration['timeouts']['max_execution_time']);
    }

    $this->runThread($lock_id, $thread, $jobs);
    $lock->unlock($lock_id);
  }

  /**
   * {@inheritdoc}
   */
  public function runThread($lock_id, $thread, $jobs) {
    $lock = \Drupal::service('ultimate_cron.lock');
    $lock_name = 'ultimate_cron_serial_launcher_' . $thread;
    foreach ($jobs as $job) {
      $configuration = $job->getConfiguration('launcher');
      $configuration['launcher'] += array(
        'thread' => 'any',
      );
      switch ($configuration['launcher']['thread']) {
        case 'any':
          $configuration['launcher']['thread'] = $thread;
          break;

        case 'fixed':
          $configuration['launcher']['thread'] = ($job->getUniqueID() % $configuration['launcher']['max_threads']) + 1;
          break;
      }
      if ((!self::getGlobalOption('thread') || $configuration['launcher']['thread'] == $thread) && $job->isScheduled()) {
        $this->launch($job);
        // Be friendly, and check if someone else has taken the lock.
        // If they have, bail out, since someone else is now handling
        // this thread.
        if ($current_lock_id = $lock->isLocked($lock_name)) {
          if ($current_lock_id !== $lock_id) {
            return;
          }
        }
        else {
          // If lock is free, then take the lock again.
          $lock_id = $lock->lock($lock_name);
          if (!$lock_id) {
            // Race-condition, someone beat us to it.
            return;
          }
        }
      }
    }
  }

}
