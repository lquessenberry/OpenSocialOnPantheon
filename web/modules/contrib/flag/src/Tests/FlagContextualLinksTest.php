<?php
/**
 * @file
 * Contains \Drupal\flag\Tests\FlagContextualLinksTest.
 */

namespace Drupal\flag\Tests;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Drupal\flag\FlagInterface;
use Drupal\Component\Utility\Html;

/**
 * Test the contextual links with Reload link type.
 *
 * @group flag
 */
class FlagContextualLinksTest extends FlagTestBase {
  /**
   * The flag.
   *
   * @var \Drupal\flag\Flaginterface
   */
  protected $flag;

  /**
   * * An authenticated user to test flagging.
   *
   * @var \Drupal\user\Entity\User|false
   */
  protected $normalUser;

  public function setUp() {
    parent::setUp();

    // Create a regular user who will be flagging content.
    $this->normalUser = $this->drupalCreateUser();
  }

  /**
   * Create a new flag with the Reload link type and enable contextual links display.
   */
  public function testNodeLinks() {
    $this->doCreateFlag();
    $this->doFlagNode();
  }

  /**
   * Create a node type and flag.
   */
  public function doCreateFlag() {
    $this->drupalLogin($this->adminUser);

    $edit = [
      'id' => 'test_label_123',
      'show_as_field' => FALSE,
      'show_contextual_link' => TRUE,
    ];

    $this->flag = $this->createFlagWithForm('node', $edit);
  }

  /**
   * Create a node and flag it.
   */
  public function doFlagNode() {
    // Grant the flag permissions to the authenticated role.
    $this->grantFlagPermissions($this->flag);

    $node = $this->drupalCreateNode(['type' => $this->nodeType]);

    // Login as normal user.
    $this->drupalLogin($this->normalUser);

    // Open node view page that renders Full view mode.
    $this->drupalGet('node/' . $node->id());

    // Expect Contextual links id for this node to contain flag_keys metadata.
    $contextual_links_id = 'node:node=' . $node->id() . ':changed=' . $node->getChangedTime() . '&flag_keys=' . $this->flag->id() . '-flag&langcode=en';
    $this->assertContextualLinkPlaceHolder($contextual_links_id);

    $flag_action_url = $this->getFlagContextualLinkUrl($this->flag, $node, $contextual_links_id, 'flag');
    $this->assertTrue($flag_action_url, "Flag link found in contextual links.");

    // Emulate click on flag link in Contextual links.
    $this->drupalGet($flag_action_url);

    // Expect Contextual links id for this node to contain flag_keys metadata with unflag action.
    $contextual_links_id = 'node:node=' . $node->id() . ':changed=' . $node->getChangedTime() . '&flag_keys=' . $this->flag->id() . '-unflag&langcode=en';
    $this->assertContextualLinkPlaceHolder($contextual_links_id);

    $flag_action_url = $this->getFlagContextualLinkUrl($this->flag, $node, $contextual_links_id, 'unflag');
    $this->assertTrue($flag_action_url, "Unflag link found in contextual links.");

    // Login as normal user.
    $this->drupalLogin($this->drupalCreateUser());

    // Open node view page that renders Full view mode.
    $this->drupalGet('node/' . $node->id());

    // Expect Contextual links id for this node to contain flag_keys metadata.
    $contextual_links_id = 'node:node=' . $node->id() . ':changed=' . $node->getChangedTime() . '&flag_keys=' . $this->flag->id() . '-flag&langcode=en';
    $this->assertContextualLinkPlaceHolder($contextual_links_id);

    $flag_action_url = $this->getFlagContextualLinkUrl($this->flag, $node, $contextual_links_id, 'flag');
    $this->assertTrue($flag_action_url, "Flag link found in contextual links.");
  }

  /**
   * Get server-rendered contextual links for the given contextual link ids.
   *
   * @see \Drupal\contextual\Tests\ContextualDynamicContextTest::renderContextualLinks().
   * @param array $ids
   *   An array of contextual link ids.
   * @param string $current_path
   *   The Drupal path for the page for which the contextual links are rendered.
   *
   * @return string
   *   The response body.
   */
  protected function renderContextualLinks($ids, $current_path) {
    $post = array();
    for ($i = 0; $i < count($ids); $i++) {
      $post['ids[' . $i . ']'] = $ids[$i];
    }
    return $this->drupalPostWithFormat('contextual/render', 'json', $post, array('query' => array('destination' => $current_path)));
  }

  /**
   * Helper method that returns a flag action URL from contextual links markup
   * by contextual links id.
   *
   * @param string $contextual_links_id
   *   Contextual links placeholder ids string.
   * @return \Drupal\Core\Url|FALSE
   *   Returns either a flag action URL object or FALSE in case that flag link
   *   wasn't found in the contextual links markup.
   */
  protected function getFlagContextualLinkUrl(FlagInterface $flag,
                                              NodeInterface $node,
                                              $contextual_links_id,
                                              $flag_action) {
    // Render contextual links JSON response and check for flag link.
    $response = $this->renderContextualLinks([$contextual_links_id], 'node');
    $this->assertResponse(200);
    $json = Json::decode($response);
    $flag_url = Url::fromRoute("flag.action_link_{$flag_action}", array(
      'flag' => $flag->id(),
      'entity_id' => $node->id(),
    ));

    $flag_short_text = $flag_action == 'flag' ? $flag->getFlagShortText() : $flag->getUnflagShortText();

    $matches = array();
    $url_pattern =  preg_quote(base_path()) . '(' . preg_quote($flag_url->getInternalPath(), '@') . '[^"]*)';
    $found = preg_match('@' . preg_quote('<ul class="contextual-links"><li class="flag-test-label-123"><a href="') .
      $url_pattern . preg_quote('">' . Html::escape($flag_short_text) . '</a></li></ul>', '@') . '@',
      $json[$contextual_links_id], $matches);

    if ($found) {
      $options = UrlHelper::parse($matches[1]);
      return Url::fromUri('internal:/' . $options['path'], $options);
    }

    return FALSE;
  }
}
