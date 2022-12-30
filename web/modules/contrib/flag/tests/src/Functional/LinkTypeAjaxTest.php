<?php

namespace Drupal\Tests\flag\Functional;

/**
 * Tests the AJAX link type.
 *
 * Links should still function on browsers with javascript disabled.
 *
 * For test with javascript enabled see
 * Drupal\Tests\flag\FunctionalJavascript\AjaxLinkTest
 *
 * @group flag
 */
class LinkTypeAjaxTest extends FlagTestBase {

  /**
   * The flag under test.
   *
   * @var \Drupal\flag\FlagInterface
   */
  protected $flag;

  /**
   * The node to be flagged and unflagged.
   *
   * @var \Drupal\node\NodeInterface
   */
  protected $node;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    // Create a node to flag.
    $this->node = $this->drupalCreateNode(['type' => $this->nodeType]);
  }

  /**
   * Tests the no-js fallback behavior for the AJAX link type.
   */
  public function testNoJavascriptResponse() {
    // Login as the admin user.
    $this->drupalLogin($this->adminUser);

    // Create the flag with the AJAX link type using the form.
    $this->flag = $this->createFlagWithForm('node', [], 'ajax_link');

    // Grant flag permissions.
    $this->grantFlagPermissions($this->flag);

    // Create and login as an authenticated user.
    $auth_user = $this->drupalCreateUser();
    $this->drupalLogin($auth_user);

    $node_url = $this->node->toUrl();

    // Navigate to the node page.
    $this->drupalGet($node_url);

    // Confirm the flag link exists.
    $this->assertLink($this->flag->getShortText('flag'));

    // Click the flag link. This ensures that the non-JS fallback works we are
    // redirected to back to the page and the node is flagged.
    $this->clickLink($this->flag->getShortText('flag'));
    $this->assertUrl($node_url);
    $this->assertLink($this->flag->getShortText('unflag'));

    // Click the unflag link, repeat the check.
    $this->clickLink($this->flag->getShortText('unflag'));
    $this->assertUrl($node_url);
    $this->assertLink($this->flag->getShortText('flag'));
  }

}
