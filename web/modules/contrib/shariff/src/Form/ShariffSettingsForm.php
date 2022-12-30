<?php

namespace Drupal\shariff\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;
use Drupal\Component\Utility\UrlHelper;

/**
 * Provides a settings form for the Shariff sharing buttons.
 */
class ShariffSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'shariff_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('shariff.settings');

    $ignoreArray = ['actions', 'form_build_id', 'form_token', 'form_id'];

    foreach (Element::children($form) as $variable) {

      $value = $form_state->getValue($form[$variable]['#parents']);

      if ($variable == 'shariff_services') {
        $value = array_filter($value);
      }

      if (!in_array($variable, $ignoreArray)) {
        $config->set($variable, $value);
      }
    }
    $config->save();

    if (method_exists($this, '_submitForm')) {
      $this->_submitForm($form, $form_state);
    }

    parent::submitForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $backend_url = $form_state->getValue('shariff_backend_url');
    if ($backend_url && !(UrlHelper::isValid($backend_url, TRUE))) {
      $form_state->setErrorByName('shariff_backend_url',
        $this->t('Please enter a valid URL.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['shariff.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $settings = _shariff_get_settings();

    $form['shariff_services'] = [
      '#title' => $this->t('Activated services'),
      '#description' => $this->t('Please define for which services a sharing button should be included.'),
      '#type' => 'checkboxes',
      '#options' => [
        'twitter' => $this->t('Twitter'),
        'facebook' => $this->t('Facebook'),
        'linkedin' => $this->t('LinkedIn'),
        'pinterest' => $this->t('Pinterest'),
        'vk' => $this->t('VK'),
        'xing' => $this->t('Xing'),
        'whatsapp' => $this->t('WhatsApp'),
        'addthis' => $this->t('AddThis'),
        'telegram' => $this->t('Telegram'),
        'tumblr' => $this->t('Tumblr'),
        'flattr' => $this->t('Flattr'),
        'diaspora' => $this->t('Diaspora'),
        'reddit' => $this->t('reddit'),
        'stumbleupon' => $this->t('StumbleUpon'),
        'weibo' => $this->t('Weibo'),
        'flipboard' => $this->t('Flipboard'),
        'pocket' => $this->t('Pocket'),
        'print' => $this->t('Print'),
        'tencent-weibo' => $this->t('Tencent-Weibo'),
        'qzone' => $this->t('Qzone'),
        'threema' => $this->t('Threema'),
        'mail' => $this->t('E-Mail'),
        'info' => $this->t('Info Button'),
        'buffer' => $this->t('Buffer'),
      ],
      '#default_value' => $settings['services'],
    ];

    $form['shariff_theme'] = [
      '#title' => $this->t('Theme'),
      '#description' => $this->t('Please choose a layout option.'),
      '#type' => 'radios',
      '#options' => [
        'colored' => $this->t('Colored'),
        'grey' => $this->t('Grey'),
        'white' => $this->t('White'),
      ],
      '#default_value' => $settings['shariff_theme'],
    ];

    $form['shariff_css'] = [
      '#title' => $this->t('CSS'),
      '#description' => $this->t('Please choose a CSS variant. Font Awesome is used to display the services icons.'),
      '#type' => 'radios',
      '#options' => [
        'complete' => $this->t('Complete (Contains also Font Awesome)'),
        'min' => $this->t('Minimal (If Font Awesome is already included in your site)'),
        'naked' => $this->t('None (Without any CSS)'),
      ],
      '#default_value' => \Drupal::config('shariff.settings')->get('shariff_css'),
    ];

    $form['shariff_button_style'] = [
      '#title' => $this->t('Button Style'),
      '#description' => $this->t('Please choose a button style.
      With "icon only" the icon is shown, with "icon-count" icon and counter and with "standard icon", text and counter are shown, depending on the display size.
      Please note: For showing counters you have to provide a working Shariff backend URL.'),
      '#type' => 'radios',
      '#options' => [
        'standard' => $this->t('Standard'),
        'icon' => $this->t('Icon'),
        'icon-count' => $this->t('Icon Count'),
      ],
      '#default_value' => $settings['button_style'],
    ];

    $form['shariff_orientation'] = [
      '#title' => $this->t('Orientation'),
      '#description' => $this->t('Vertical will stack the buttons vertically. Default is horizontally.'),
      '#type' => 'radios',
      '#options' => [
        'vertical' => $this->t('Vertical'),
        'horizontal' => $this->t('Horizontal'),
      ],
      '#default_value' => $settings['orientation'],
    ];

    $form['shariff_twitter_via'] = [
      '#title' => $this->t('Twitter Via User'),
      '#description' => $this->t('Screen name of the Twitter user to attribute the Tweets to.'),
      '#type' => 'textfield',
      '#default_value' => $settings['twitter_via'],
    ];

    $form['shariff_mail_url'] = [
      '#title' => $this->t('Mail link'),
      '#description' => $this->t('The url target used for the mail service button. Leave it as "mailto:" to let the user
 choose an email address.'),
      '#type' => 'textfield',
      '#default_value' => $settings['mail_url'] ? $settings['mail_url'] : 'mailto:',
    ];

    $form['shariff_mail_subject'] = [
      '#title' => $this->t('Mail subject'),
      '#description' => $this->t("If a mailto: link is provided in Mail link above, then this value is used as the mail subject.
 Left empty the page's current (canonical) URL or og:url is used."),
      '#type' => 'textfield',
      '#default_value' => $settings['mail_subject'],
    ];

    $form['shariff_mail_body'] = [
      '#title' => $this->t('Mail body'),
      '#description' => $this->t("If a mailto: link is provided in Mail link above, then this value is used as the mail body.
 Left empty the page title is used."),
      '#type' => 'textarea',
      '#default_value' => $settings['mail_body'],
    ];

    $form['shariff_referrer_track'] = [
      '#title' => $this->t('Referrer track code'),
      '#description' => $this->t('A string that will be appended to the share url. Disabled when empty.'),
      '#type' => 'textfield',
      '#default_value' => $settings['referrer_track'],
    ];

    $form['shariff_backend_url'] = [
      '#title' => $this->t('Backend URL'),
      '#description' => $this->t('The path to your Shariff backend. Leaving the value blank disables the backend feature and no counts will occur.'),
      '#type' => 'textfield',
      '#default_value' => $settings['backend_url'],
    ];

    $form['shariff_flattr_category'] = [
      '#title' => $this->t('Flattr category'),
      '#description' => $this->t('Category to be used for Flattr.'),
      '#type' => 'textfield',
      '#default_value' => $settings['flattr_category'],
    ];

    $form['shariff_flattr_user'] = [
      '#title' => $this->t('Flattr user'),
      '#description' => $this->t('User that receives Flattr donation.'),
      '#type' => 'textfield',
      '#default_value' => $settings['flattr_user'],
    ];

    $form['shariff_media_url'] = [
      '#title' => $this->t('Media url'),
      '#description' => $this->t('Media url to be shared (Pinterest).'),
      '#type' => 'textfield',
      '#default_value' => $settings['media_url'],
    ];

    $form['shariff_info_url'] = [
      '#title' => $this->t('Shariff Information URL'),
      '#description' => $this->t('The url for information about Shariff. Used by the Info Button.'),
      '#type' => 'url',
      '#default_value' => $settings['info_url'],
    ];

    $form['shariff_info_display'] = [
      '#title' => $this->t('Shariff Information Page Display'),
      '#description' => $this->t('How the above URL should be opened. Please choose a display option.'),
      '#type' => 'radios',
      '#options' => [
        'blank' => $this->t('Blank'),
        'popup' => $this->t('Popup'),
        'self' => $this->t('Self'),
      ],
      '#default_value' => $settings['info_display'],
    ];

    $form['shariff_title'] = [
      '#title' => $this->t('WhatsApp/Twitter Share Title'),
      '#description' => $this->t('Fixed title to be used as share text in Twitter/Whatsapp.
      Normally you want to leave it as it is, then page\'s DC.title/DC.creator or page title is used.'),
      '#type' => 'textfield',
      '#default_value' => $settings['title'],
    ];

    $form['shariff_url'] = [
      '#title' => $this->t('Canonical URL'),
      '#description' => $this->t('You can fix the canonical URL of the page to check here.
         Normally you want to leave it as it is, then the page\'s canonical URL or og:url or current URL is used.'),
      '#type' => 'textfield',
      '#default_value' => $settings['url'],
    ];

    return parent::buildForm($form, $form_state);
  }

}
