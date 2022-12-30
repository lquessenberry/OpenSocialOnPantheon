<?php

namespace Drupal\bootstrap\Plugin\Setting\JavaScript\Modals;

use Drupal\bootstrap\Plugin\Setting\SettingBase;
use Drupal\bootstrap\Utility\Element;
use Drupal\Core\Form\FormStateInterface;

/**
 * The "modal_animation" theme setting.
 *
 * @ingroup plugins_setting
 *
 * @BootstrapSetting(
 *   id = "modal_animation",
 *   type = "checkbox",
 *   title = @Translation("animation"),
 *   description = @Translation("Apply a CSS fade transition to modals."),
 *   defaultValue = 1,
 *   groups = {
 *     "javascript" = @Translation("JavaScript"),
 *     "modals" = @Translation("Modals"),
 *     "options" = @Translation("Options"),
 *   },
 * )
 */
class ModalAnimation extends SettingBase {

  /**
   * {@inheritdoc}
   */
  public function alterFormElement(Element $form, FormStateInterface $form_state, $form_id = NULL) {
    parent::alterFormElement($form, $form_state, $form_id);
    $group = $this->getGroupElement($form, $form_state);
    $group->note = [
      '#type' => 'container',
      '#weight' => -1,
      '#attributes' => ['class' => ['alert', 'alert-info', 'alert-sm']],
      0 => [
        '#markup' => $this->t('<strong>Note:</strong> jQuery UI dialog options will be mapped to Bootstrap modal options whenever possible, however they always take precedent over any global Bootstrap modal options set here for compatibility reasons.'),
      ],
      '#states' => [
        'visible' => [
          ':input[name="modal_enabled"]' => ['checked' => TRUE],
          ':input[name="modal_jquery_ui_bridge"]' => ['checked' => TRUE],
        ],
      ],
    ];
    $group->setProperty('description', $this->t('These are global options. Each modal can independently override desired settings by appending the option name to <code>data-</code>. Example: <code>data-backdrop="false"</code>.'));
    $group->setProperty('states', [
      'visible' => [
        ':input[name="modal_enabled"]' => ['checked' => TRUE],
      ],
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function drupalSettings() {
    return !!$this->theme->getSetting('modal_enabled');
  }

}
