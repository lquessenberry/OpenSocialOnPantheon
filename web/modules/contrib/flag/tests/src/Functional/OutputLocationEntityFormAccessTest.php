<?php

namespace Drupal\Tests\flag\Functional;

use Drupal\flag\Entity\Flag;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\Tests\BrowserTestBase;

/**
 * Tests the entity form checkbox output respects flag access control.
 *
 * @group flag
 */
class OutputLocationEntityFormAccessTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'system',
    'user',
    'node',
    'flag',
    'flag_test_plugins',
  ];

  /**
   * The node whose edit form is shown.
   *
   * @var \Drupal\node\Entity\Node
   */
  protected $node;

  /**
   * A flag that grants access.
   *
   * @var \Drupal\flag\Entity\Flag
   */
  protected $flag_granted;

  /**
   * A flag that denies access.
   *
   * @var \Drupal\flag\Entity\Flag
   */
  protected $flag_denied;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    NodeType::create(['type' => 'page', 'name' => 'page'])->save();
    $this->node = Node::create(['type' => 'page', 'title' => 'test']);
    $this->node->save();

    $this->flag_granted = Flag::create([
      'id' => 'flag_granted',
      'label' => 'Flag allowed',
      'entity_type' => 'node',
      'bundles' => ['page'],
      // Use dummy flag type plugins that return a known access value so we're
      // not involving the actual access system.
      'flag_type' => 'test_access_granted',
      'link_type' => 'reload',
      'flagTypeConfig' => [
        'show_on_form' => TRUE,
      ],
      'linkTypeConfig' => [],
      'flag_short' => 'Flag this',
      'unflag_short' => 'Unflag this',
    ]);
    $this->flag_granted->save();

    $this->flag_denied = Flag::create([
      'id' => 'flag_denied',
      'label' => 'Flag denied',
      'entity_type' => 'node',
      'bundles' => ['page'],
      'flag_type' => 'test_access_denied',
      'link_type' => 'reload',
      'flagTypeConfig' => [
        'show_on_form' => TRUE,
      ],
      'linkTypeConfig' => [],
      'flag_short' => 'Flag this',
      'unflag_short' => 'Unflag this',
    ]);
    $this->flag_denied->save();

    // Create and login as an authenticated user.
    $auth_user = $this->drupalCreateUser([
      'access content',
      'edit any page content',
    ]);
    $this->drupalLogin($auth_user);
  }

  /**
   * Tests the access to the flag checkbox in the node edit form.
   */
  public function testCheckboxAccess() {
    // Get the node edit form.
    $this->drupalGet("node/" . $this->node->id() . "/edit");

    $this->assertSession()->pageTextContains('Flag allowed', 'The checkbox for the flag with access is shown.');
    $this->assertSession()->pageTextNotContains('Flag denied', 'The checkbox for the flag without access is not shown.');
  }

}
