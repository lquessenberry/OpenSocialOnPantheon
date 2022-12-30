<?php

namespace Drupal\Tests\select2\FunctionalJavascript;

use Behat\Mink\Element\NodeElement;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\Tests\select2\Traits\Select2TestTrait;

/**
 * Class Select2JavascriptTestBase.
 *
 * Base class for select2 Javascript tests.
 */
abstract class Select2JavascriptTestBase extends WebDriverTestBase {

  use Select2TestTrait;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['node', 'select2', 'options'];

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();

    $this->drupalCreateContentType(['type' => 'test']);

    $user = $this->drupalCreateUser([
      'access content',
      'edit own test content',
      'create test content',
    ]);

    $this->drupalLogin($user);
  }

  /**
   * Selects an option in a select2 widget.
   *
   * @param string $field
   *   Name of the field.
   * @param array $keys
   *   Values for the field.
   */
  protected function selectOption($field, array $keys) {
    $this->getSession()->executeScript("jQuery('#$field').val(['" . implode("', '", $keys) . "'])");
    $this->getSession()->executeScript("jQuery('#$field').trigger('change')");
  }

  /**
   * Scroll element with defined css selector in middle of browser view.
   *
   * @param string $cssSelector
   *   CSS Selector for element that should be centralized.
   */
  protected function scrollElementInView($cssSelector) {
    $this->getSession()
      ->executeScript('
        var viewPortHeight = Math.max(document.documentElement.clientHeight, window.innerHeight || 0);
        var element = jQuery(\'' . addcslashes($cssSelector, '\'') . '\');
        var scrollTop = element.offset().top - (viewPortHeight/2);
        var scrollableParent = jQuery.isFunction(element.scrollParent) ? element.scrollParent() : [];
        if (scrollableParent.length > 0 && scrollableParent[0] !== document && scrollableParent[0] !== document.body) { scrollableParent[0].scrollTop = scrollTop } else { window.scroll(0, scrollTop); };
      ');
  }

  /**
   * Drag element in document with defined offset position.
   *
   * @param \Behat\Mink\Element\NodeElement $element
   *   Element that will be dragged.
   * @param int $offsetX
   *   Vertical offset for element drag in pixels.
   * @param int $offsetY
   *   Horizontal offset for element drag in pixels.
   */
  protected function dragDropElement(NodeElement $element, $offsetX, $offsetY) {
    $this->assertSession()->assertWaitOnAjaxRequest();

    $elemXpath = addslashes($element->getXpath());

    $jsCode = "var fireMouseEvent = function (type, element, x, y) {"
      . "  var event = document.createEvent('MouseEvents');"
      . "  event.initMouseEvent(type, true, (type !== 'mousemove'), window, 0, 0, 0, x, y, false, false, false, false, 0, element);"
      . "  element.dispatchEvent(event); };";

    // XPath provided by getXpath uses single quote (') to encapsulate strings,
    // that's why xpath has to be quited with double quites in javascript code.
    $jsCode .= "(function() {" .
      "  var dragElement = document.evaluate(\"{$elemXpath}\", document, null, XPathResult.FIRST_ORDERED_NODE_TYPE, null).singleNodeValue;" .
      "  var pos = dragElement.getBoundingClientRect();" .
      "  var centerX = Math.floor((pos.left + pos.right) / 2);" .
      "  var centerY = Math.floor((pos.top + pos.bottom) / 2);" .
      "  fireMouseEvent('mousedown', dragElement, centerX, centerY);" .
      "  fireMouseEvent('mousemove', document, centerX + {$offsetX}, centerY + {$offsetY});" .
      "  fireMouseEvent('mouseup', dragElement, centerX + {$offsetX}, centerY + {$offsetY});" .
      "})();";

    $this->getSession()->executeScript($jsCode);
  }

}
