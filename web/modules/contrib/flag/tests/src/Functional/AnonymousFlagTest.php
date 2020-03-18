<?php

namespace Drupal\Tests\flag\Functional;

use Behat\Mink\Session;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\flag\Entity\Flag;
use Drupal\flag\Entity\Flagging;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\Tests\BrowserTestBase;
use Drupal\user\Entity\Role;

/**
 * @group flag
 */
class AnonymousFlagTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = ['system', 'user', 'node', 'flag'];

  /**
   * @var \Drupal\node\Entity\Node
   */
  protected $node;

  /**
   * @var \Drupal\flag\Entity\Flag
   */
  protected $flag;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    NodeType::create(['type' => 'page', 'name' => 'page'])->save();
    $this->node = Node::create(['type' => 'page', 'title' => 'test']);
    $this->node->save();
    $flag_id = strtolower($this->randomMachineName());
    $this->flag = Flag::create([
      'id' => $flag_id,
      'label' => $this->randomString(),
      'entity_type' => 'node',
      'bundles' => ['page'],
      'flag_type' => 'entity:node',
      'link_type' => 'reload',
      'flagTypeConfig' => [],
      'linkTypeConfig' => [],
      'flag_short' => 'switch_this_on',
      'unflag_short' => 'switch_this_off'
    ]);
    $this->flag->save();

    Role::load(Role::ANONYMOUS_ID)
      ->grantPermission('flag '. $flag_id)
      ->grantPermission('unflag '. $flag_id)
      ->save();
  }

  /**
   * Tests flagging as an anonymous user.
   */
  public function testAnonymousFlagging() {
    $this->drupalGet(Url::fromRoute('entity.node.canonical', ['node' => $this->node->id()]));
    $this->getSession()->getPage()->clickLink('switch_this_on');
    $this->assertNotEmpty($this->getSession()->getPage()->findLink('switch_this_off'));
    // Warning: $this->getDatabaseConnection() is the original database
    // connection, not the current one.
    $flagging_id = \Drupal::database()->query('SELECT id FROM {flagging}')->fetchField();
    $this->assertNotEmpty($flagging_id);

    $flagging = Flagging::load($flagging_id);
    // Check that the session ID value in the flagging is the same as the user's
    // cookie ID.
    $session_id = $this->getSession()->getCookie($this->getSessionName());
    $this->assertEqual($flagging->get('session_id')->value, $session_id, "The flagging entity has the session ID set.");

    // Try another anonymous user.
    $old_mink = $this->mink;
    $this->initMink();
    $this->drupalGet(Url::fromRoute('entity.node.canonical', ['node' => $this->node->id()]));
    $this->assertNotEmpty($this->getSession()->getPage()->findLink('switch_this_on'));

    // Switch back to the original.
    $this->mink = $old_mink;
    // Unflag the node.
    $this->getSession()->getPage()->clickLink('switch_this_off');
    $this->assertNotEmpty($this->getSession()->getPage()->findLink('switch_this_on'));

    // Clear the storage cache so we load fresh entities.
    $this->container->get('entity_type.manager')->getStorage('flagging')->resetCache();

    $flagging = Flagging::load($flagging_id);
    $this->assertEmpty($flagging, "The first user's flagging was deleted.");
  }

}
