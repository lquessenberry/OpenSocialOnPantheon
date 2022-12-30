<?php

namespace Drupal\r4032login\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configure r4032login global settings for this site.
 */
class SettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'r4032login_settings_form';
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

    $form['default_redirect_code'] = [
      '#type' => 'select',
      '#title' => $this->t("HTTP redirect code"),
      '#description' => $this->t('The redirect code to send by default. 301 and 302 responses may be cached by browsers and proxies, so 307 is normally the correct choice.'),
      '#options' => [
        '307' => $this->t('307 Temporary Redirect'),
        '302' => $this->t('302 Found'),
        '301' => $this->t('301 Moved Permanently'),
      ],
      '#default_value' => $config->get('default_redirect_code'),
    ];

    $form['add_noindex_header'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Add <code>X-Robots-Tag: noindex</code> header'),
      '#description' => $this->t('Should a noindex tag be added to the response header? This tells search engines not to index the page.'),
      '#default_value' => $config->get('add_noindex_header'),
    ];

    $form['matching_paths'] = [
      '#type' => 'details',
      '#title' => $this->t('Skip redirect for matching pages'),
      '#open' => TRUE,
    ];
    $form['matching_paths']['match_noredirect_negate'] = [
      '#type' => 'radios',
      '#options' => [
        $this->t('Skip redirect for listed pages'),
        $this->t('Allow redirect for listed pages'),
      ],
      '#default_value' => $config->get('match_noredirect_negate'),
    ];
    $form['matching_paths']['match_noredirect_pages'] = [
      '#type' => 'textarea',
      '#title' => '<span class="element-invisible">' . $this->t('Pages') . '</span>',
      '#default_value' => $config->get('match_noredirect_pages'),
      '#description' => $this->t("Specify pages by using their paths. Enter one path per line. The '*' character is a wildcard. Example paths are %blog for the blog page and %blog-wildcard for every personal blog. %front is the front page.", [
        '%blog' => '/blog',
        '%blog-wildcard' => '/blog/*',
        '%front' => '<front>',
      ]),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('r4032login.settings')
      ->set('default_redirect_code', $form_state->getValue('default_redirect_code'))
      ->set('add_noindex_header', $form_state->getValue('add_noindex_header'))
      ->set('match_noredirect_negate', $form_state->getValue('match_noredirect_negate'))
      ->set('match_noredirect_pages', $form_state->getValue('match_noredirect_pages'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
