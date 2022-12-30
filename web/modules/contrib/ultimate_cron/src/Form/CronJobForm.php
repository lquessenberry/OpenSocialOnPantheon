<?php

namespace Drupal\ultimate_cron\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\ultimate_cron\CronPlugin;
use Drupal\ultimate_cron\CronRule;

/**
 * Base form controller for cron job forms.
 */
class CronJobForm extends EntityForm {

  protected $selected_option;

  /**
   * @var \Drupal\ultimate_cron\CronJobInterface
   */
  protected $entity;

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);
    /* @var \Drupal\ultimate_cron\Entity\CronJob $job */
    $job = $this->entity;

    $form['title'] = array(
      '#title' => t('Title'),
      '#description' => t('This will appear in the administrative interface to easily identify it.'),
      '#type' => 'textfield',
      '#default_value' => $job->getTitle(),
    );

    $form['id'] = array(
      '#type' => 'machine_name',
      '#default_value' => $job->id(),
      '#machine_name' => array(
        'exists' => '\Drupal\ultimate_cron\Entity\CronJob::load',
        'source' => array('title'),
      ),
      '#disabled' => !$job->isNew(),
    );

    $form['status'] = array(
      '#type' => 'checkbox',
      '#title' => t('Enabled'),
      '#default_value' => $job->status(),
      '#description' => t('This checkbox enables the cron job. Disabled Cron jobs are not run.'),
    );

    $form['module_info'] = array(
      '#type' => 'item',
      '#title' => $this->t('Module'),
      '#markup' => $job->getModule(),
    );

    $callback = $job->getCallback();
    if (is_array($callback)) {
      $callback = get_class($callback[0]) . '::' . $callback[1];
    }

    $form['callback_info'] = array(
      '#type' => 'item',
      '#title' => $this->t('Callback'),
      '#markup' => $callback,
    );

    // Setup vertical tabs.
    $form['settings_tabs'] = array(
      '#type' => 'vertical_tabs',
    );

    // Load settings for each plugin in its own vertical tab.
    $plugin_types = CronPlugin::getPluginTypes();
    foreach ($plugin_types as $plugin_type => $plugin_label) {
      /* @var \Drupal\Core\Plugin\DefaultPluginManager $manager */
      $manager = \Drupal::service('plugin.manager.ultimate_cron.' . $plugin_type);
      $plugins = $manager->getDefinitions();

      $plugin_settings = $job->get($plugin_type);

      // Generate select options.
      $options = array();
      foreach ($plugins as $value => $key) {
        if (!empty($key['default']) && $key['default'] == TRUE) {
          $options = array($value => t('@title (Default)', array('@title' => $key['title']))) + $options;
        }
        else {
          $options[$value] = $key['title'];
        }
      }

      $form[$plugin_type] = array(
        '#type' => 'details',
        '#title' => $plugin_label,
        '#group' => 'settings_tabs',
        '#tree' => TRUE,
      );

      $form[$plugin_type]['id'] = array(
        '#type' => 'select',
        '#title' => $plugin_label,
        '#options' => $options,
        '#plugin_type' => $plugin_type,
        '#default_value' => $plugin_settings['id'],
        '#description' => $this->t("Select which @plugin to use for this job.", array('@plugin' => $plugin_type)),
        '#group' => 'settings_tabs',
        '#executes_submit_callback' => TRUE,
        '#ajax' => array(
          'callback' => array($this, 'updateSelectedPluginType'),
          'wrapper' => $plugin_type . '_settings',
          'method' => 'replace',
        ),
        '#submit' => array('::submitForm', '::rebuild'),
        '#limit_validation_errors' => array(array($plugin_type, 'id')),
      );

      $form[$plugin_type]['select'] = array(
        '#type' => 'submit',
        '#name' => $plugin_type . '_select',
        '#value' => t('Select'),
        '#submit' => array('::submitForm', '::rebuild'),
        '#limit_validation_errors' => array(array($plugin_type, 'id')),
        '#attributes' => array('class' => array('js-hide')),
      );

      $plugin = $job->getPlugin($plugin_type);
      $temp_form = array();
      $form[$plugin_type]['configuration'] = $plugin->buildConfigurationForm($temp_form, $form_state);
      $form[$plugin_type]['configuration']['#prefix'] = '<div id="' . $plugin_type . '_settings' . '">';
      $form[$plugin_type]['configuration']['#suffix'] = '</div>';
    }

    //$form['#attached']['js'][] = drupal_get_path('module', 'ultimate_cron') . '/js/ultimate_cron.job.js';

    return $form;
  }

  public function updateSelectedPluginType(array $form, FormStateInterface $form_state) {
    return $form[$form_state->getTriggeringElement()['#plugin_type']]['configuration'];
  }

  /**
   * {@inheritdoc}
   */
  public function rebuild(array $form, FormStateInterface $form_state) {
    $form_state->setRebuild(TRUE);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);

    $this->entity->getPlugin('scheduler')->validateConfigurationForm($form, $form_state);

  }

  /**
   * Overrides Drupal\Core\Entity\EntityForm::save().
   */
  public function save(array $form, FormStateInterface $form_state) {
    parent::save($form, $form_state);
    $this->messenger()
      ->addStatus(t('job %label has been updated.', ['%label' => $this->entity->label()]));
    $form_state->setRedirect('entity.ultimate_cron_job.collection');

  }

}
