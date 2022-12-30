<?php

namespace Drupal\url_embed\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configure Url Embed settings for this site.
 */
class UrlEmbedAdminSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'url_embed_admin_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['url_embed.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('url_embed.settings');

    $form['facebook_app'] = [
      '#type' => 'details',
      '#open' => TRUE,
      '#title' => $this->t('Facebook / Instagram App'),
      '#description' => $this->t('As of Oct 24 2020, Facebook and Instagram require you to create a live <a href="@doc_url" target="_blank">facebook app with an oEmbed product enabled</a> to embed posts.', [
        '@doc_url' => 'https://developers.facebook.com/docs/plugins/oembed',
      ]),
    ];

    // If we have Facebook credentials in the config, check that it is a valid app and show a message to the user.
    if (!empty($config->get('facebook_app_id')) && !empty($config->get('facebook_app_id'))) {
      $debug = url_embed_debug_facebook_access_token($config->get('facebook_app_id') . '|' . $config->get('facebook_app_secret'));
      if (!empty($debug['is_valid'])) {
        $form['facebook_app']['facebook_app_status']['#markup'] = '<div class="messages messages--status">' . $this->t('The Facebook app is active.') . '</div>';
      }
      else {
        $form['facebook_app']['facebook_app_status']['#markup'] = '<div class="messages messages--warning">' . $this->t('The App ID and App Secret combination is invalid. Make sure you entered your app credentials correctly.') . '</div>';
      }
    }

    $form['facebook_app']['facebook_app_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('App ID'),
      '#default_value' => $config->get('facebook_app_id'),
    ];

    $form['facebook_app']['facebook_app_secret'] = [
      '#type' => 'textfield',
      '#title' => $this->t('App Secret'),
      '#default_value' => $config->get('facebook_app_secret'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('url_embed.settings');
    $config
      ->set('facebook_app_id', $form_state->getValue('facebook_app_id'))
      ->set('facebook_app_secret', $form_state->getValue('facebook_app_secret'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
