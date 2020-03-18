<?php

namespace Drupal\FunctionalJavascriptTests\Ajax;

use Drupal\FunctionalJavascriptTests\JavascriptTestBase;

/**
 * Tests AJAX responses.
 *
 * @group Ajax
 */
class AjaxTest extends JavascriptTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = ['ajax_test'];

  /**
   * Wraps HTML with an AJAX target element.
   *
   * This reproduces recognizable parts of the wrapping markup from
   * \Drupal\ajax_test\Controller\AjaxTestController::insertLinks and is not
   * supposed to return valid HTML.
   *
   * @param string $html
   *   The HTML to wrap.
   *
   * @return string
   *   The HTML wrapped in the an AJAX target element.
   *
   * @see \Drupal\ajax_test\Controller\AjaxTestController::insertLinks
   */
  protected function wrapAjaxTarget($html) {
    return 'data-drupal-ajax-target="">' . $html . '</';
  }

  public function testAjaxWithAdminRoute() {
    \Drupal::service('theme_installer')->install(['stable', 'seven']);
    $theme_config = \Drupal::configFactory()->getEditable('system.theme');
    $theme_config->set('admin', 'seven');
    $theme_config->set('default', 'stable');
    $theme_config->save();

    $account = $this->drupalCreateUser(['view the administration theme']);
    $this->drupalLogin($account);

    // First visit the site directly via the URL. This should render it in the
    // admin theme.
    $this->drupalGet('admin/ajax-test/theme');
    $assert = $this->assertSession();
    $assert->pageTextContains('Current theme: seven');

    // Now click the modal, which should also use the admin theme.
    $this->drupalGet('ajax-test/dialog');
    $assert->pageTextNotContains('Current theme: stable');
    $this->clickLink('Link 8 (ajax)');
    $assert->assertWaitOnAjaxRequest();

    $assert->pageTextContains('Current theme: stable');
    $assert->pageTextNotContains('Current theme: seven');
  }

  /**
   * Test that AJAX loaded libraries are not retained between requests.
   *
   * @see https://www.drupal.org/node/2647916
   */
  public function testDrupalSettingsCachingRegression() {
    $this->drupalGet('ajax-test/dialog');
    $assert = $this->assertSession();
    $session = $this->getSession();

    // Insert a fake library into the already loaded library settings.
    $fake_library = 'fakeLibrary/fakeLibrary';
    $session->evaluateScript("drupalSettings.ajaxPageState.libraries = drupalSettings.ajaxPageState.libraries  ',$fake_library';");

    $libraries = $session->evaluateScript('drupalSettings.ajaxPageState.libraries');
    // Test that the fake library is set.
    $this->assertContains($fake_library, $libraries);

    // Click on the AJAX link.
    $this->clickLink('Link 8 (ajax)');
    $assert->assertWaitOnAjaxRequest();

    // Test that the fake library is still set after the AJAX call.
    $libraries = $session->evaluateScript('drupalSettings.ajaxPageState.libraries');
    $this->assertContains($fake_library, $libraries);

    // Reload the page, this should reset the loaded libraries and remove the
    // fake library.
    $this->drupalGet('ajax-test/dialog');
    $libraries = $session->evaluateScript('drupalSettings.ajaxPageState.libraries');
    $this->assertNotContains($fake_library, $libraries);

    // Click on the AJAX link again, and the libraries should still not contain
    // the fake library.
    $this->clickLink('Link 8 (ajax)');
    $assert->assertWaitOnAjaxRequest();
    $libraries = $session->evaluateScript('drupalSettings.ajaxPageState.libraries');
    $this->assertNotContains($fake_library, $libraries);
  }

/**
   * Tests that various AJAX responses with DOM elements are correctly inserted.
   *
   * After inserting DOM elements, Drupal JavaScript behaviors should be
   * reattached and all top-level elements of type Node.ELEMENT_NODE need to be
   * part of the context.
   *
   * @dataProvider providerTestInsert
   */
  public function testInsertBlock($render_type, $expected) {
    $assert = $this->assertSession();

    $this->drupalGet('ajax-test/insert-block-wrapper');
    $this->clickLink("Link html $render_type");
    $assert->assertWaitOnAjaxRequest();
    // Extra span added by a second prepend command on the ajax requests.
    $assert->responseContains($this->wrapAjaxTarget($expected));

    $this->drupalGet('ajax-test/insert-block-wrapper');
    $this->clickLink("Link replaceWith $render_type");
    $assert->assertWaitOnAjaxRequest();
    $assert->responseContains($expected);
    $assert->responseNotContains($this->wrapAjaxTarget($expected));
  }

  /**
   * Tests that various AJAX responses with DOM elements are correctly inserted.
   *
   * After inserting DOM elements, Drupal JavaScript behaviors should be
   * reattached and all top-level elements of type Node.ELEMENT_NODE need to be
   * part of the context.
   *
   * @dataProvider providerTestInsert
   */
  public function testInsertInline($render_type, $expected) {
    $assert = $this->assertSession();

    $this->drupalGet('ajax-test/insert-inline-wrapper');
    $this->clickLink("Link html $render_type");
    $assert->assertWaitOnAjaxRequest();
    // Extra span added by a second prepend command on the ajax requests.
    $assert->responseContains($this->wrapAjaxTarget($expected));

    $this->drupalGet('ajax-test/insert-inline-wrapper');
    $this->clickLink("Link replaceWith $render_type");
    $assert->assertWaitOnAjaxRequest();
    $assert->responseContains($expected);
    $assert->responseNotContains($this->wrapAjaxTarget($expected));
  }

  /**
   * Provides test result data.
   */
  public function providerTestInsert() {
    $test_cases = [];

    // Test that no additional wrapper is added when inserting already wrapped
    // response data and all top-level node elements (context) are processed
    // correctly.
    $test_cases['pre_wrapped_div'] = [
      'pre-wrapped-div',
      '<div class="pre-wrapped processed">pre-wrapped<script> var test;</script></div>',
    ];
    $test_cases['pre_wrapped_span'] = [
      'pre-wrapped-span',
      '<span class="pre-wrapped processed">pre-wrapped<script> var test;</script></span>',
    ];
    // Test that no additional empty leading div is added when the return
    // value had a leading space and all top-level node elements (context) are
    // processed correctly.
    $test_cases['pre_wrapped_whitespace'] = [
      'pre-wrapped-whitespace',
      " <div class=\"pre-wrapped-whitespace processed\">pre-wrapped-whitespace</div>\n",
    ];
    // Test that not wrapped response data (text node) is inserted wrapped and
    // all top-level node elements (context) are processed correctly.
    $test_cases['not_wrapped'] = [
      'not-wrapped',
      'not-wrapped',
    ];
    // Test that not wrapped response data (text node and comment node) is
    // inserted wrapped and all top-level node elements
    // (context) are processed correctly.
    $test_cases['comment_string_not_wrapped'] = [
      'comment-string-not-wrapped',
      '<!-- COMMENT -->comment-string-not-wrapped',
    ];
    // Test that top-level comments (which are not lead by text nodes) are
    // inserted without wrapper.
    $test_cases['comment_not_wrapped'] = [
      'comment-not-wrapped',
      '<!-- COMMENT --><div class="comment-not-wrapped processed">comment-not-wrapped</div>',
    ];
    // Test that mixed inline & block level elements and comments response data
    // is inserted correctly.
    $test_cases['mixed'] = [
      'mixed',
      ' foo <!-- COMMENT -->  foo bar<div class="a class processed"><p>some string</p></div> additional not wrapped strings, <!-- ANOTHER COMMENT --> <p class="processed">final string</p>',
    ];
    // Test that when the response has only top-level node elements, they
    // are processed properly without extra wrapping.
    $test_cases['top_level_only'] = [
      'top-level-only',
      '<div class="processed">element #1</div><div class="processed">element #2</div>',
    ];
    // Test that whitespaces at start or end don't wrap the response when
    // there are multiple top-level nodes.
    $test_cases['top_level_only_pre_whitespace'] = [
      'top-level-only-pre-whitespace',
      ' <div class="processed">element #1</div><div class="processed">element #2</div> ',
    ];
    // Test when there are whitespaces between top-level divs.
    $test_cases['top_level_only_middle_whitespace-div'] = [
      'top-level-only-middle-whitespace-div',
      '<div class="processed">element #1</div> <div class="processed">element #2</div>',
    ];
    // Test when there are whitespaces between top-level spans.
    $test_cases['top_level_only_middle_whitespace-span'] = [
      'top-level-only-middle-whitespace-span',
      '<span class="processed">element #1</span> <span class="processed">element #2</span>',
    ];
    // Test that empty response data.
    $test_cases['empty'] = [
      'empty',
      '',
    ];

    return $test_cases;
  }

}
