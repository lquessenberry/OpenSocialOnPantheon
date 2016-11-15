<?php

namespace Drupal\flag\Tests;

/**
 * Tests the AJAX link type.
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
   * Test the AJAX link type.
   */
  public function testAjaxLinkType() {
    $this->doCreateAjaxFlag();
    $this->doUseAjaxFlag();
  }

  /**
   * Create a flag using the ajax_link link type using the UI.
   */
  public function doCreateAjaxFlag() {
    // Login as the admin user.
    $this->drupalLogin($this->adminUser);

    // Create the flag with the AJAX link type using the form.
    $this->flag = $this->createFlagWithForm('node', [], 'ajax_link');

    // Grant flag permissions.
    $this->grantFlagPermissions($this->flag);
  }

  /**
   * Test an AJAX flag link.
   */
  public function doUseAjaxFlag() {
    // Create and login as an authenticated user.
    $auth_user = $this->drupalCreateUser();
    $this->drupalLogin($auth_user);

    $node_url = $this->node->toUrl();

    // Navigate to the node page.
    $this->drupalGet($node_url);

    // Confirm the flag link exists.
    $this->assertLink($this->flag->getFlagShortText());

    // Click the flag link. This ensures that the non-JS fallback works we are
    // redirected to back to the page and the node is flagged.
    $this->clickLink($this->flag->getFlagShortText());
    $this->assertUrl($node_url);
    $this->assertLink($this->flag->getUnflagShortText());

    // Click the unflag link, repeat the check.
    $this->clickLink($this->flag->getUnflagShortText());
    $this->assertUrl($node_url);
    $this->assertLink($this->flag->getFlagShortText());

    // Now also test with an ajax request and that the correct response is
    // returned. Use the same logic as clickLink() to find the link.
    $urls = $this->xpath('//a[normalize-space()=:label]', array(':label' => $this->flag->getFlagShortText()));
    $url_target = $this->getAbsoluteUrl($urls[0]['href']);
    $ajax_response = $this->drupalGetAjax($url_target);

    // Assert that the replace selector is correct.
    $id_class_position = strpos($urls[0]['class'], ltrim($ajax_response[0]['selector'], '.'));
    $this->assertTrue($id_class_position !== FALSE);

    // Request the returned URL to ensure that link is valid and has a valid
    // CSRF token.
    $xml_data = new \SimpleXMLElement($ajax_response[0]['data']);
    $this->assertEqual($this->flag->getUnflagShortText(), (string) $xml_data);

    $ajax_response = $this->drupalGetAjax($this->getAbsoluteUrl($xml_data['href']));

    // Assert that the replace selector is correct.
    $id_class_position = strpos($xml_data['class'], ltrim($ajax_response[0]['selector'], '.'));
    $this->assertTrue($id_class_position !== FALSE);

    $xml_data_unflag = new \SimpleXMLElement($ajax_response[0]['data']);
    $this->assertEqual($this->flag->getFlagShortText(), (string) $xml_data_unflag);

  }

}
