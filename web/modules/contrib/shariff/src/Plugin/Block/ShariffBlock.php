<?php

namespace Drupal\shariff\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Cache\Cache;

/**
 * Provides a 'shariff' block.
 *
 * @Block(
 *   id = "shariff_block",
 *   admin_label = @Translation("Shariff share buttons"),
 *   category = @Translation("Blocks"),
 * )
 */
class ShariffBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    $config = $this->getConfiguration();
    $shariff_settings = [];
    foreach ($config as $setting => $value) {
      // Only set shariff settings as variable.
      if (substr($setting, 0, strlen('shariff')) === 'shariff') {
        $shariff_settings[$setting] = $value;
      }
    }
    // Set variable when block should overwrite default settings.
    $blocksettings = (isset($config['shariff_default_settings']) && $config['shariff_default_settings']) ? NULL : $shariff_settings;
    $block = [
      '#theme' => 'block_shariff',
      '#blocksettings' => $blocksettings,
      '#attached' => [
        'library' => [
          'shariff/shariff',
        ],
      ],
    ];
    return $block;
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $form = parent::blockForm($form, $form_state);

    // Retrieve existing configuration for this block.
    $config = $this->getConfiguration();

    $form['shariff_default_settings'] = [
      '#title' => $this->t('Use Shariff default settings'),
      '#description' => $this->t('When set default Shariff settings are used. Uncheck to overwrite settings here.'),
      '#type' => 'checkbox',
      '#default_value' => isset($config['shariff_default_settings']) ? $config['shariff_default_settings'] : TRUE,
    ];

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
        'flipboard' => $this->t('Flipboard'),
        'pocket' => $this->t('Pocket'),
        'print' => $this->t('Print'),
        'reddit' => $this->t('reddit'),
        'stumbleupon' => $this->t('StumbleUpon'),
        'threema' => $this->t('Threema'),
        'mail' => $this->t('E-Mail'),
        'info' => $this->t('Info Button'),
        'buffer' => $this->t('Buffer'),
      ],
      '#default_value' => isset($config['shariff_services']) ? $config['shariff_services'] : \Drupal::config('shariff.settings')->get('shariff_services'),
      '#states' => [
        // Only show this field when the 'shariff_default_settings' checkbox
        // is enabled.
        'visible' => [
          ':input[name="settings[shariff_default_settings]"]' => ['checked' => FALSE],
        ],
      ],
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
      '#default_value' => isset($config['shariff_theme']) ? $config['shariff_theme'] : \Drupal::config('shariff.settings')->get('shariff_theme'),
      '#states' => [
        // Only show this field when the 'shariff_default_settings' checkbox
        // is enabled.
        'visible' => [
          ':input[name="settings[shariff_default_settings]"]' => ['checked' => FALSE],
        ],
      ],
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
      '#default_value' => isset($config['shariff_css']) ? $config['shariff_css'] : \Drupal::config('shariff.settings')->get('shariff_css'),
      '#states' => [
        // Only show this field when the 'shariff_default_settings' checkbox
        // is enabled.
        'visible' => [
          ':input[name="settings[shariff_default_settings]"]' => ['checked' => FALSE],
        ],
      ],
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
      '#default_value' => isset($config['shariff_button_style']) ? $config['shariff_button_style'] : \Drupal::config('shariff.settings')->get('shariff_button_style'),
      '#states' => [
        // Only show this field when the 'shariff_default_settings' checkbox
        // is enabled.
        'visible' => [
          ':input[name="settings[shariff_default_settings]"]' => ['checked' => FALSE],
        ],
      ],
    ];

    $form['shariff_orientation'] = [
      '#title' => $this->t('Orientation'),
      '#description' => $this->t('Vertical will stack the buttons vertically. Default is horizontally.'),
      '#type' => 'radios',
      '#options' => [
        'vertical' => $this->t('Vertical'),
        'horizontal' => $this->t('Horizontal'),
      ],
      '#default_value' => isset($config['shariff_orientation']) ? $config['shariff_orientation'] : \Drupal::config('shariff.settings')->get('shariff_orientation'),
      '#states' => [
        // Only show this field when the 'shariff_default_settings' checkbox
        // is enabled.
        'visible' => [
          ':input[name="settings[shariff_default_settings]"]' => ['checked' => FALSE],
        ],
      ],
    ];

    $form['shariff_twitter_via'] = [
      '#title' => $this->t('Twitter Via User'),
      '#description' => $this->t('Screen name of the Twitter user to attribute the Tweets to.'),
      '#type' => 'textfield',
      '#default_value' => isset($config['shariff_twitter_via']) ? $config['shariff_twitter_via'] : \Drupal::config('shariff.settings')->get('shariff_twitter_via'),
      '#states' => [
        // Only show this field when the 'shariff_default_settings' checkbox
        // is enabled.
        'visible' => [
          ':input[name="settings[shariff_default_settings]"]' => ['checked' => FALSE],
        ],
      ],
    ];

    $form['shariff_mail_url'] = [
      '#title' => $this->t('Mail link'),
      '#description' => $this->t('The url target used for the mail service button. Leave it as "mailto:" to let the user
 choose an email address.'),
      '#type' => 'textfield',
      '#default_value' => isset($config['shariff_mail_url']) ? $config['shariff_mail_url'] : \Drupal::config('shariff.settings')->get('shariff_mail_url'),
      '#states' => [
        // Only show this field when the 'shariff_default_settings' checkbox
        // is enabled.
        'visible' => [
          ':input[name="settings[shariff_default_settings]"]' => ['checked' => FALSE],
        ],
      ],
    ];

    $form['shariff_mail_subject'] = [
      '#title' => $this->t('Mail subject'),
      '#description' => $this->t("If a mailto: link is provided in Mail link above, then this value is used as the mail subject.
 Left empty the page's current (canonical) URL or og:url is used."),
      '#type' => 'textfield',
      '#default_value' => isset($config['shariff_mail_subject']) ? $config['shariff_mail_subject'] : \Drupal::config('shariff.settings')->get('shariff_mail_subject'),
      '#states' => [
        // Only show this field when the 'shariff_default_settings' checkbox
        // is enabled.
        'visible' => [
          ':input[name="settings[shariff_default_settings]"]' => ['checked' => FALSE],
        ],
      ],
    ];

    $form['shariff_mail_body'] = [
      '#title' => $this->t('Mail body'),
      '#description' => $this->t("If a mailto: link is provided in Mail link above, then this value is used as the mail body.
 Left empty the page title is used."),
      '#type' => 'textarea',
      '#default_value' => isset($config['shariff_mail_body']) ? $config['shariff_mail_body'] : \Drupal::config('shariff.settings')->get('shariff_mail_body'),
      '#states' => [
        // Only show this field when the 'shariff_default_settings' checkbox
        // is enabled.
        'visible' => [
          ':input[name="settings[shariff_default_settings]"]' => ['checked' => FALSE],
        ],
      ],
    ];

    $form['shariff_referrer_track'] = [
      '#title' => $this->t('Referrer track code'),
      '#description' => $this->t('A string that will be appended to the share url. Disabled when empty.'),
      '#type' => 'textfield',
      '#default_value' => isset($config['shariff_referrer_track']) ? $config['shariff_referrer_track'] : \Drupal::config('shariff.settings')->get('shariff_referrer_track'),
      '#states' => [
        // Only show this field when the 'shariff_default_settings' checkbox
        // is enabled.
        'visible' => [
          ':input[name="settings[shariff_default_settings]"]' => ['checked' => FALSE],
        ],
      ],
    ];

    $form['shariff_backend_url'] = [
      '#title' => $this->t('Backend URL'),
      '#description' => $this->t('The path to your Shariff backend. Leaving the value blank disables the backend feature and no counts will occur.'),
      '#type' => 'textfield',
      '#default_value' => isset($config['shariff_backend_url']) ? $config['shariff_backend_url'] : \Drupal::config('shariff.settings')->get('shariff_backend_url'),
      '#states' => [
        // Only show this field when the 'shariff_default_settings' checkbox
        // is enabled.
        'visible' => [
          ':input[name="settings[shariff_default_settings]"]' => ['checked' => FALSE],
        ],
      ],
    ];

    $form['shariff_flattr_category'] = [
      '#title' => $this->t('Flattr category'),
      '#description' => $this->t('Category to be used for Flattr.'),
      '#type' => 'textfield',
      '#default_value' => isset($config['shariff_flattr_category']) ? $config['shariff_flattr_category'] : \Drupal::config('shariff.settings')->get('shariff_flattr_category'),
      '#states' => [
        // Only show this field when the 'shariff_default_settings' checkbox
        // is enabled.
        'visible' => [
          ':input[name="settings[shariff_default_settings]"]' => ['checked' => FALSE],
        ],
      ],
    ];

    $form['shariff_flattr_user'] = [
      '#title' => $this->t('Flattr user'),
      '#description' => $this->t('User that receives Flattr donation.'),
      '#type' => 'textfield',
      '#default_value' => isset($config['shariff_flattr_user']) ? $config['shariff_flattr_user'] : \Drupal::config('shariff.settings')->get('shariff_flattr_user'),
      '#states' => [
        // Only show this field when the 'shariff_default_settings' checkbox
        // is enabled.
        'visible' => [
          ':input[name="settings[shariff_default_settings]"]' => ['checked' => FALSE],
        ],
      ],
    ];

    $form['shariff_media_url'] = [
      '#title' => $this->t('Media url'),
      '#description' => $this->t('Media url to be shared (Pinterest).'),
      '#type' => 'textfield',
      '#default_value' => isset($config['shariff_media_url']) ? $config['shariff_media_url'] : \Drupal::config('shariff.settings')->get('shariff_media_url'),
      '#states' => [
        // Only show this field when the 'shariff_default_settings' checkbox
        // is enabled.
        'visible' => [
          ':input[name="settings[shariff_default_settings]"]' => ['checked' => FALSE],
        ],
      ],
    ];

    $form['shariff_info_url'] = [
      '#title' => $this->t('Shariff Information URL'),
      '#description' => $this->t('The url for information about Shariff. Used by the Info Button.'),
      '#type' => 'url',
      '#default_value' => isset($config['shariff_info_url']) ? $config['shariff_info_url'] : \Drupal::config('shariff.settings')
        ->get('shariff_info_url'),
      '#states' => [
        // Only show this field when the 'shariff_default_settings' checkbox
        // is enabled.
        'visible' => [
          ':input[name="settings[shariff_default_settings]"]' => ['checked' => FALSE],
        ],
      ],
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
      '#default_value' => isset($config['shariff_info_display']) ? $config['shariff_info_display'] : \Drupal::config('shariff.settings')
        ->get('shariff_info_display'),
      '#states' => [
        // Only show this field when the 'shariff_default_settings' checkbox
        // is enabled.
        'visible' => [
          ':input[name="settings[shariff_default_settings]"]' => ['checked' => FALSE],
        ],
      ],
    ];

    $form['shariff_title'] = [
      '#title' => $this->t('WhatsApp/Twitter Share Title'),
      '#description' => $this->t('Fixed title to be used as share text in Twitter/Whatsapp.
      Normally you want to leave it as it is, then page\'s DC.title/DC.creator or page title is used.'),
      '#type' => 'textfield',
      '#default_value' => isset($config['shariff_title']) ? $config['shariff_title'] : \Drupal::config('shariff.settings')
        ->get('shariff_title'),
      '#states' => [
        // Only show this field when the 'shariff_default_settings' checkbox
        // is enabled.
        'visible' => [
          ':input[name="settings[shariff_default_settings]"]' => ['checked' => FALSE],
        ],
      ],
    ];

    $form['shariff_url'] = [
      '#title' => $this->t('Canonical URL'),
      '#description' => $this->t('You can fix the canonical URL of the page to check here.
         Normally you want to leave it as it is, then the page\'s canonical URL or og:url or current URL is used.'),
      '#type' => 'textfield',
      '#default_value' => isset($config['shariff_url']) ? $config['shariff_url'] : \Drupal::config('shariff.settings')
        ->get('shariff_url'),
      '#states' => [
        // Only show this field when the 'shariff_default_settings' checkbox
        // is enabled.
        'visible' => [
          ':input[name="settings[shariff_default_settings]"]' => ['checked' => FALSE],
        ],
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    // Save our custom settings when the form is submitted.
    $values = $form_state->getValues();
    $this->setConfigurationValue('shariff_default_settings', $form_state->getValue('shariff_default_settings'));
    // Only save values when default settings should be overwritten.
    if (!$form_state->getValue('shariff_default_settings')) {
      foreach ($values as $setting => $value) {
        $this->setConfigurationValue($setting, $form_state->getValue($setting));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function blockValidate($form, FormStateInterface $form_state) {

    $backend_url = $form_state->getValue('shariff_backend_url');
    if ($backend_url && !(UrlHelper::isValid($backend_url, TRUE))) {
      $this->messenger()->addError('Please enter a valid Backend URL.');
      // TODO: Get rid of drupal_set_message() when
      // https://www.drupal.org/node/2537732 is fixed.
      // setErrorByName is not working for now.
      $form_state->setErrorByName('shariff_backend_url', t('Please enter a valid URL.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts() {
    // The shariff block must be cached per URL, as the URL will be shared.
    return Cache::mergeContexts(parent::getCacheContexts(), ['url']);
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags() {
    // The block output is dependent on the shariff settings form.
    return Cache::mergeTags(parent::getCacheTags(), ['config:shariff.settings']);
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheMaxAge() {
    // When displaying share counts the block needs to be updated regularly.
    // For now we set this value to 1 hour. Todo: Make it configurable.
    return 3600;
  }

}
