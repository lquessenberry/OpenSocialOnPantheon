<?php

/**
 * @file
 * Contains \Drupal\votingapi\Tests\VoteTest.
 */

namespace Drupal\votingapi\Tests;

use Drupal\simpletest\WebTestBase;

/**
 * Tests the Voting API basics.
 *
 * @group VotingAPI
 */
class VoteTest extends WebTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = ['node', 'votingapi', 'votingapi_test'];

  /**
   * Tests casting a vote on an entity.
   */
  public function testVotes() {
    $vote_query = $this->container->get('entity.query')->get('vote');
    $vote_storage = $this->container->get('entity.manager')->getStorage('vote');
    $node = $this->drupalCreateNode(['type' => 'article']);
    $user = $this->drupalCreateUser();

    // There are no votes on this entity yet
    $query = $vote_query->condition('entity_type', 'node')
      ->condition('entity_id', $node->id());
    $votes = $query->execute();
    $this->assertEqual(count($votes), 0, 'Vote count for a node is initially zero.');

    // Add a vote to a node.
    /** @var \Drupal\votingapi\VoteInterface $vote */
    $vote = $vote_storage->create([
      'type' => 'type',
      'entity_id' => $node->id(),
      'entity_type' => 'node',
      'user_id' => $user->id(),
      'value' => -1,
    ]);
    $vote->save();
    $votes = $query->execute();
    $this->assertEqual(count($votes), 1, 'After a vote is cast on a node, it can be retrieved.');
    $vote = $vote_storage->load(reset($votes));
    $this->assertNotNull($vote, 'Node vote was loaded.');
    $this->assertEqual($vote->getOwnerId(), $user->id(), 'Node vote has correct user.');
    $this->assertEqual($vote->getValue(), -1, 'Node vote has correct value.');
    $this->assertNotEqual($vote->getSource(), '', 'A vote with no explicit source received the default value.');

    // Add a vote to a user
    $vote = $vote_storage->create([
      'type' => 'vote',
      'entity_id' => $user->id(),
      'entity_type' => 'user',
    ]);
    $vote->save();

    $vote_query = $this->container->get('entity.query')->get('vote');
    $query = $vote_query->condition('entity_type', 'user')
      ->condition('entity_id', $user->id());
    $votes = $query->execute();
    $this->assertEqual(count($votes), 1, 'After a vote is cast on a user, it can be retrieved.');
    $vote = $vote_storage->load(reset($votes));
    $this->assertNotNull($vote, 'User vote was loaded.');
    $this->assertEqual($vote->getOwnerId(), 0, 'A vote with no explicit user received the default value.');
    $this->assertEqual($vote->getValue(), 0, 'A vote with no explicit value received the default value.');

    // Deleting entity deletes votes
    entity_delete_multiple('user', [$user->id()]);
    $votes = $query->execute();
    $this->assertEqual(count($votes), 0, 'When an entity is deleted, the votes are also deleted.');
  }

  /**
   * Test vote results.
   */
  public function testVoteResults() {
    $vote_storage = $this->container->get('entity.manager')->getStorage('vote');
    $node = $this->drupalCreateNode();
    $user = $this->drupalCreateUser();
    $manager = $this->container->get('plugin.manager.votingapi.resultfunction');

    // Save a few votes so that we have data.
    $values = [10, 20, 60];
    foreach ($values as $value) {
      $vote_storage->create([
        'type' => 'vote',
        'entity_id' => $node->id(),
        'entity_type' => 'node',
        'user_id' => $user->id(),
        'value' => $value,
      ])->save();
    }

    $results = $manager->getResults('node', $node->id());

    // Standard results are available and correct.
    $this->assertFalse(empty($results['vote']), 'Results for test vote type are available.');
    $this->assertTrue(isset($results['vote']['vote_sum']), 'Sum was calculated.');
    $this->assertEqual($results['vote']['vote_sum'], 90, 'Sum is correct.');
    $this->assertTrue(isset($results['vote']['vote_average']), 'Average was calculated.');
    $this->assertEqual($results['vote']['vote_average'], 30, 'Average is correct.');

    // When you remove a result type via the hook, it is not longer available.
    $this->assertTrue(empty($results['test']['vote_count']), 'Result removed via alter hook was not calculated.');

    // Contrib modules can add new result types.
    // @todo This isn't working, as $results are pulled directly from the
    // database via the getResults() method.
    // $this->assertTrue(isset($results['vote']['zebra']), 'New result was calculated.');
    // $this->assertEqual($results['vote']['zebra'], 10101, 'New result is correct.');

    // Deleting entity removes results
    entity_delete_multiple('node', [$node->id()]);
    $results = $manager->getResults('node', $node->id());
    $this->assertTrue(empty($results), 'When an entity is deleted, the voting results are also deleted.');
  }

  /**
   * Test voting by anonymous users.
   */
  public function testAnonymousVoting() {
    $vote_storage = $this->container->get('entity.manager')->getStorage('vote');
    $node = $this->drupalCreateNode();

    // Save a few votes from different anonymous users.
    $values = [
      10 => 'source_1',
      20 => 'source_2',
      60 => 'source_2',
    ];
    foreach ($values as $value => $source) {
      $vote_storage->create([
        'type' => 'vote',
        'entity_id' => $node->id(),
        'entity_type' => 'node',
        'user_id' => 0,
        'value' => $value,
        'vote_source' => $source,
      ])->save();
    }

    // Retrieve the votes. For now, just count them.
    $votes_from_source_1 = $vote_storage->getUserVotes(0, 'vote', 'node', 1, 'source_1');
    $votes_from_source_2 = $vote_storage->getUserVotes(0, 'vote', 'node', 1, 'source_2');
    $this->assertEqual(count($votes_from_source_1), 1, 'There is 1 vote from the first source.');
    $this->assertEqual(count($votes_from_source_2), 2, 'There are 2 votes from the second source.');

    // Delete the votes from source_2 and repeat the test.
    $vote_storage->deleteUserVotes(0, 'vote', 'node', 1, 'source_2');
    $votes_from_source_1 = $vote_storage->getUserVotes(0, 'vote', 'node', 1, 'source_1');
    $votes_from_source_2 = $vote_storage->getUserVotes(0, 'vote', 'node', 1, 'source_2');
    $this->assertEqual(count($votes_from_source_1), 1, 'There is still 1 vote from the first source.');
    $this->assertEqual(count($votes_from_source_2), 0, 'There are now 0 votes from the second source.');
  }

}
