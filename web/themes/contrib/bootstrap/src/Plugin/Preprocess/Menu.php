<?php

namespace Drupal\bootstrap\Plugin\Preprocess;

use Drupal\bootstrap\Utility\Attributes;
use Drupal\bootstrap\Utility\Variables;
use Drupal\Core\Template\Attribute;
use Drupal\Core\Url;

/**
 * Pre-processes variables for the "menu" theme hook.
 *
 * @ingroup plugins_preprocess
 *
 * @BootstrapPreprocess("menu")
 */
class Menu extends PreprocessBase implements PreprocessInterface {

  /**
   * {@inheritdoc}
   */
  protected function preprocessVariables(Variables $variables) {
    $this->convertAttributes($variables->items);
  }

  /**
   * Converts attributes to core's Attribute class.
   *
   * @param array $items
   *   The menu items.
   */
  protected function convertAttributes(array &$items) {
    foreach ($items as &$item) {
      $wrapperAttributes = new Attributes();
      $linkAttributes = new Attributes();
      if ($item['attributes'] instanceof Attribute || $item['attributes'] instanceof Attributes) {
        $wrapperAttributes->setAttributes($item['attributes']->getIterator()->getArrayCopy());
      }
      if ($item['url'] instanceof Url) {
        $wrapperAttributes->setAttributes($item['url']->getOption('wrapper_attributes') ?: []);
        $wrapperAttributes->setAttributes($item['url']->getOption('container_attributes') ?: []);
        $linkAttributes->setAttributes($item['url']->getOption('attributes') ?: []);

        // If URL isn't a link, it's rendered as a <span> element. Add the
        // "navbar-text" class so it doesn't disrupt the navbar items.
        // @see https://www.drupal.org/project/bootstrap/issues/3053464
        if ($item['url']->isRouted() && $item['url']->getRouteName() === '<nolink>') {
          $linkAttributes->addClass('navbar-text');
        }
      }

      // Unfortunately, in newer core/Twig versions, only certain classes are
      // allowed to be invoked due to stricter sandboxing policies. To get
      // around this, just rewrap attributes in core's native Attribute class.
      $item['attributes'] = new Attribute($wrapperAttributes->getArrayCopy());
      $item['link_attributes'] = new Attribute($linkAttributes->getArrayCopy());
      if (!empty($item['below']) && is_array($item['below'])) {
        $this->convertAttributes($item['below']);
      }
    }
  }

}
