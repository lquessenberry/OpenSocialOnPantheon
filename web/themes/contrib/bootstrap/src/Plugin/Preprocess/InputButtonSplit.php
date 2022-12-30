<?php

namespace Drupal\bootstrap\Plugin\Preprocess;

use Drupal\bootstrap\Utility\Element;
use Drupal\bootstrap\Utility\Variables;

/**
 * Pre-processes variables for the "input__button__split" theme hook.
 *
 * @ingroup plugins_preprocess
 *
 * @BootstrapPreprocess("input__button__split")
 */
class InputButtonSplit extends InputButton implements PreprocessInterface {

  /**
     * {@inheritdoc}
     */
  public function preprocessElement(Element $element, Variables $variables) {
        $variables['default_button'] = $element->getProperty('default_button');
        parent::preprocessElement($element, $variables);
      }

}
