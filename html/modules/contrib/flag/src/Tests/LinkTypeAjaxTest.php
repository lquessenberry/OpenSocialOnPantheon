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

    /* Assert that initially a flag action link is displayed within a wrapper.
     *
     * NB the xpath template to search for a div with a class member of a
     * flag :-
     *
     * div[contains(concat(' ',normalize-space(@class),' '),' flag ')]
     */
    $links = $this->xpath('//div[contains(concat(" ",normalize-space(@class)," ")," flag ")]/a[normalize-space()=:label]', array(':label' => $this->flag->getShortText('flag')));
    // Use the same logic as clickLink() to get an AJAX response.
    $link_target = $this->getAbsoluteUrl($links[0]['href']);
    $flag_response = $this->drupalGetAjax($link_target);

    // $flag_response is a AJAX replace command with a string as the data
    // payload. Convert the payload string into a HTML fragment for inspection.
    $flag_xml_data = new \SimpleXMLElement($flag_response[0]['data']);

    // The replace command in the AJAX response has a selector.
    // Assert the selector identifies the div wrapping action link.
    $id_class_position = strpos($flag_xml_data['class']->__toString(), ltrim($flag_response[0]['selector'], '.'));
    $this->assertTrue($id_class_position !== FALSE);

    // Assert the flag response contains a wrapped unflag action link.
    $this->assertEqual($this->flag->getShortText('unflag'), $flag_xml_data->a->__toString());

    // From the payload extract a unflag action link href.
    // Act as if the unflag link has been clicked.
    $unflag_response = $this->drupalGetAjax($this->getAbsoluteUrl($flag_xml_data->a['href']));
    $unflag_xml_data = new \SimpleXMLElement($unflag_response[0]['data']);

    // Assert that the replace selector is correct.
    $unflag_class_position = strpos($unflag_xml_data['class'], ltrim($unflag_response[0]['selector'], '.'));
    $this->assertTrue($unflag_class_position !== FALSE);

    // Assert the unflag response contains a wrapped flag action link.
    $this->assertEqual($this->flag->getShortText('flag'), $unflag_xml_data->a->__toString());

  }

}
