<?php

/**
 * @file
 * Contains \Drupal\votingapi\Tests\VoteCreationTest.
 */

namespace Drupal\votingapi\Tests;

use Drupal\node\Entity\Node;
use Drupal\simpletest\WebTestBase;
use Drupal\votingapi\Entity\Vote;

/**
 * Tests the Voting API basics.
 *
 * @group VotingAPI
 */
class VoteCreationTest extends WebTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = ['node', 'votingapi', 'votingapi_test'];

  /**
   * A simple user with basic node and vote permissions
   *
   * @var \Drupal\user\Entity\User
   */
  protected $logged_user;

  /**
   * A simple user vote permission
   *
   * @var \Drupal\user\Entity\User
   */
  protected $anonymous_user;

  /**
   * @var \Drupal\node\Entity\Node
   */
  private $node;

  /**
   * @var \Drupal\votingapi\Entity\Vote
   */
  private $vote;

  protected function setUp() {
    parent::setUp();

    // Create Basic page and Article node types.
    if ($this->profile != 'standard') {
      $node_type = $this->drupalCreateContentType([
        'type' => 'page',
        'name' => 'Basic page',
        'display_submitted' => FALSE,
      ]);
      node_add_body_field($node_type);
    }

    $this->logged_user = $this->drupalCreateUser();

    $this->drupalLogin($this->logged_user);

    $title = $this->randomMachineName(8);

    $this->node = Node::create(['type' => 'page']);
    $this->node->setOwner($this->loggedInUser);
    $this->node->setTitle($title);
    $this->node->set('body', ['value' => $this->randomMachineName(16)]);
    $this->node->save();

    $this->node = $this->drupalGetNodeByTitle($title);
    $this->assertTrue($this->node, 'Basic page created for Voting API tests.');
  }

  /**
   * Test voting with non-existent Vote Type Id
   */
  public function testVoteCreationWithInvalidVoteType() {


    $this->vote = Vote::create(['type' => 'vote']);
    //    $this->vote = Vote::create(['type' => 'fake_vote_type']);
    $this->vote->setVotedEntityId($this->node->id());
    $this->vote->setVotedEntityType($this->node->getEntityTypeId());
    $this->vote->setValue(50);
    $this->vote->save();
    $this->assertTrue($this->vote, 'A "fake_vote_type" vote was successfully cast on a node.');
  }

}
