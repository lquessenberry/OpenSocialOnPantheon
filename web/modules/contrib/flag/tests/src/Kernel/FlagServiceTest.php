<?php
namespace Drupal\Tests\flag\Kernel;

use Drupal\flag\Entity\Flag;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;

/**
 * Tests the FlagService.
 *
 * @group flag
 */
class FlagServiceTest extends FlagKernelTestBase {

  /**
   * Tests that flags once created can be retrieved.
   */
  public function testFlagServiceGetFlag() {
    // Create a flag.
    $flag = Flag::create([
      'id' => strtolower($this->randomMachineName()),
      'label' => $this->randomString(),
      'entity_type' => 'node',
      'bundles' => ['article'],
      'flag_type' => 'entity:node',
      'link_type' => 'reload',
      'flagTypeConfig' => [],
      'linkTypeConfig' => [],
    ]);
    $flag->save();

    // Search for flag.
    $user_with_access = $this->createUser(['flag ' . $flag->id()]);
    $result = $this->flagService->getFlags('node', 'article', $user_with_access);
    $this->assertIdentical(count($result), 1, 'Found flag type');

    // Search denied.
    $user_no_access = $this->createUser();
    $empty_result = $this->flagService->getFlags('node', 'article', $user_no_access);
    $this->assertIdentical(count($empty_result), 0, 'Flag type access denied');
  }

  /**
   * Test exceptions are thrown when flagging and unflagging.
   */
  public function testFlagServiceFlagExceptions() {
    $not_article = NodeType::create(['type' => 'not_article']);
    $not_article->save();

    // The service methods don't check access, so our user can be anybody.
    $account = $this->createUser();

    // Create a flag.
    $flag = Flag::create([
      'id' => strtolower($this->randomMachineName()),
      'label' => $this->randomString(),
      'entity_type' => 'node',
      'bundles' => ['article'],
      'flag_type' => 'entity:node',
      'link_type' => 'reload',
      'flagTypeConfig' => [],
      'linkTypeConfig' => [],
    ]);
    $flag->save();

    // Test flagging.

    // Try flagging an entity that's not a node: a user account.
    try {
      $this->flagService->flag($flag, $account, $account);
      $this->fail("The exception was not thrown.");
    }
    catch (\LogicException $e) {
      $this->pass("The flag() method throws an exception when the flag does not apply to the entity type of the flaggable entity.");
    }

    // Try flagging a node of the wrong bundle.
    $wrong_node = Node::create([
      'type' => 'not_article',
      'title' => $this->randomMachineName(8),
    ]);
    $wrong_node->save();

    try {
      $this->flagService->flag($flag, $wrong_node, $account);
      $this->fail("The exception was not thrown.");
    }
    catch (\LogicException $e) {
      $this->pass("The flag() method throws an exception when the flag does not apply to the bundle of the flaggable entity.");
    }

    // Flag the node, then try to flag it again.
    $flaggable_node = Node::create([
      'type' => 'article',
      'title' => $this->randomMachineName(8),
    ]);
    $flaggable_node->save();

    $this->flagService->flag($flag, $flaggable_node, $account);

    try {
      $this->flagService->flag($flag, $flaggable_node, $account);
      $this->fail("The exception was not thrown.");
    }
    catch (\LogicException $e) {
      $this->pass("The flag() method throws an exception when the flaggable entity is already flagged by the user with the flag.");
    }
    // Test unflagging.

    // Try unflagging an entity that's not a node: a user account.
    try {
      $this->flagService->unflag($flag, $account, $account);
      $this->fail("The exception was not thrown.");
    }
    catch (\LogicException $e) {
      $this->pass("The unflag() method throws an exception when the flag does not apply to the entity type of the flaggable entity.");
    }

    // Try unflagging a node of the wrong bundle.
    try {
      $this->flagService->unflag($flag, $wrong_node, $account);
      $this->fail("The exception was not thrown.");
    }
    catch (\LogicException $e) {
      $this->pass("The unflag() method throws an exception when the flag does not apply to the bundle of the flaggable entity.");
    }

    // Create a new node that's not flagged, and try to unflag it.
    $unflagged_node = Node::create([
      'type' => 'article',
      'title' => $this->randomMachineName(8),
    ]);
    $unflagged_node->save();
    try {
      $this->flagService->unflag($flag, $unflagged_node, $account);
      $this->fail("The exception was not thrown.");
    }
    catch (\LogicException $e) {
      $this->pass("The unflag() method throws an exception when the flaggable entity is not flagged by the user with the flag.");
    }

    // Demonstrate a valid combination can be unflagged without throwing an
    // exception.
    try {
      $this->flagService->unflag($flag, $flaggable_node, $account);
      $this->pass('The unflag() method throws no exception when the flaggable entity and user is correct');
    }
    catch (\LogicException $e){
      $this->fail('The unfag() method threw an exception where processing a valid unflag request.');
    }
  }

  /**
   * Tests that getFlaggingUsers method returns the expected result.
   */
  public function testFlagServiceGetFlaggingUsers() {
    // The service methods don't check access, so our user can be anybody.
    $accounts = array($this->createUser(), $this->createUser());

    // Create a flag.
    $flag = Flag::create([
      'id' => strtolower($this->randomMachineName()),
      'label' => $this->randomString(),
      'entity_type' => 'node',
      'bundles' => ['article'],
      'flag_type' => 'entity:node',
      'link_type' => 'reload',
      'flagTypeConfig' => [],
      'linkTypeConfig' => [],
    ]);
    $flag->save();

    // Flag the node.
    $flaggable_node = Node::create([
      'type' => 'article',
      'title' => $this->randomMachineName(8),
    ]);
    $flaggable_node->save();
    foreach ($accounts as $account) {
      $this->flagService->flag($flag, $flaggable_node, $account);
    }

    $flagging_users = $this->flagService->getFlaggingUsers($flaggable_node, $flag);
    $this->assertTrue(is_array($flagging_users), "The method getFlaggingUsers() returns an array.");

    foreach ($accounts as $account) {
      foreach ($flagging_users as $flagging_user) {
        if ($flagging_user->id() == $account->id()) {
          break;
        }
      }
      $this->assertTrue($flagging_user->id() == $account->id(), "The returned array has the flagged account included.");
    }
  }

}
