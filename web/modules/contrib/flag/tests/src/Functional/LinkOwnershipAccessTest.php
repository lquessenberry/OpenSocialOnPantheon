<?php

namespace Drupal\Tests\flag\Functional;

/**
 * Tests the current user sees links for their own flaggings, or global ones.
 *
 * @group flag
 */
class LinkOwnershipAccessTest extends FlagTestBase {

  /**
   * The flaggable entity to test.
   *
   * @var \Drupal\Core\Entity\EntityInterface
   */
  protected $node;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->entityTypeManager = $this->container->get('entity_type.manager');

    // Create a node to flag.
    $this->node = $this->drupalCreateNode(['type' => $this->nodeType]);
  }

  /**
   * Test ownership access.
   */
  public function testFlagOwnershipAccess() {
    $this->doFlagOwnershipAccessTest();
    $this->doGlobalFlagOwnershipAccessTest();
  }

  public function doFlagOwnershipAccessTest() {
    // Create a non-global flag.
    $flag = $this->createFlag();

    // Grant the flag permissions to the authenticated role, so that both
    // users have the same roles and share the render cache.
    $this->grantFlagPermissions($flag);

    // Create and login a new user.
    $user_1 = $this->drupalCreateUser();
    $this->drupalLogin($user_1);

    // Flag the node with user 1.
    $this->drupalGet($this->node->toUrl());
    $this->clickLink($flag->getShortText('flag'));
    $this->assertResponse(200);
    $this->assertLink($flag->getShortText('unflag'));

    // Switch to user 2. They should see the link to flag.
    $user_2 = $this->drupalCreateUser();
    $this->drupalLogin($user_2);
    $this->drupalGet($this->node->toUrl());
    $this->assertLink($flag->getShortText('flag'), 0, "A flag link is found on the page for user 2.");

  }

  public function doGlobalFlagOwnershipAccessTest() {
    // Create a global flag.
    $flag = $this->createGlobalFlag();

    // Grant the flag permissions to the authenticated role, so that both
    // users have the same roles and share the render cache.
    $this->grantFlagPermissions($flag);

    // Create and login a new user.
    $user_1 = $this->drupalCreateUser();
    $this->drupalLogin($user_1);

    // Flag the node with user 1.
    $this->drupalGet($this->node->toUrl());
    $this->clickLink($flag->getShortText('flag'));
    $this->assertResponse(200);
    $this->assertLink($flag->getShortText('unflag'));

    // Switch to user 2. They should see the unflag link too.
    $user_2 = $this->drupalCreateUser();
    $this->drupalLogin($user_2);
    $this->drupalGet($this->node->toUrl());
    $this->assertLink($flag->getShortText('unflag'), 0, "The unflag link is found on the page for user 2.");
  }

}
