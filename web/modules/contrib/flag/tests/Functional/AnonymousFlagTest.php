<?php

namespace Drupal\Tests\yourmodule\Functional;

use Behat\Mink\Session;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\flag\Entity\Flag;
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
  public static $modules = ['system', 'node', 'flag'];

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
    Role::load(AccountInterface::ANONYMOUS_ROLE)
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
    // Warning: $this->getDatabaseConnection() is the original database
    // connection, not the current one.
    $flagging_id = \Drupal::database()->query('SELECT id FROM {flagging}')->fetchField();
    $this->assertNotEmpty($flagging_id);
    $query = $this->getSessionQuery(serialize(['flaggings' => [(string) $flagging_id]]));
    $this->assertNotEmpty($query->execute()->fetchField());

    // Try another anonymous user.
    $old_mink = $this->mink;
    $this->initMink();
    $this->drupalGet(Url::fromRoute('entity.node.canonical', ['node' => $this->node->id()]));
    $this->assertNotEmpty($this->getSession()->getPage()->findLink('switch_this_on'));
    // Switch back to the original.
    $this->mink = $old_mink;

    $this->getSession()->getPage()->clickLink('switch_this_off');
    $this->assertEmpty($query->execute()->fetchField());
    $query = $this->getSessionQuery(serialize(['flaggings' => []]));
    $this->assertNotEmpty($query->execute()->fetchField());
  }

  /**
   * Helper returning a session query.
   *
   * @param \Drupal\Core\Database\Connection $connection
   * @param string  $session_data
   * @return \Drupal\Core\Database\Query\SelectInterface
   */
  protected function getSessionQuery($session_data) {
    // Warning: $this->getDatabaseConnection() is the original database
    // connection, not the current one.
    $connection = \Drupal::database();
    return $connection->select('sessions', 's')
      ->fields('s', ['sid'])
      ->condition('session', '%' . $connection->escapeLike($session_data) . '%', 'LIKE');
  }

}
