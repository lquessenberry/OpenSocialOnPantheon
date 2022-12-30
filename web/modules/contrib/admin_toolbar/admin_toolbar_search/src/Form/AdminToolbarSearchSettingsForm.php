<?php

namespace Drupal\admin_toolbar_search\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configure Admin Toolbar Search settings for this site.
 */
class AdminToolbarSearchSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'admin_toolbar_search_admin_toolbar_search_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['admin_toolbar_search.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['display_menu_item'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Display the search input as a menu item.'),
      '#description' => $this->t("If set, instead of displaying a text input field, it displays a menu item in the toolbar so the user has to click on it to toggle the search input."),
      '#default_value' => $this->config('admin_toolbar_search.settings')->get('display_menu_item'),
    ];
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('admin_toolbar_search.settings')
      ->set('display_menu_item', $form_state->getValue('display_menu_item'))
      ->save();
    parent::submitForm($form, $form_state);
  }

}
