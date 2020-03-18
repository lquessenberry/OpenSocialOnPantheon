<?php

namespace Drupal\flag\Tests;

/**
 * Tests Flag module permissions.
 *
 * @group flag
 */
class FlagPermissionsTest extends FlagTestBase {

  /**
   * The flag under test.
   *
   * @var FlagInterface
   */
  protected $flag;

  /**
   * The node to flag.
   *
   * @var EntityInterface
   */
  protected $node;

  /**
   * A user who can flag and unflag.
   *
   * @var AccountInterface
   */
  protected $fullFlagUser;

  /**
   * A user who can only flag.
   *
   * @var AccountInterface
   */
  protected $flagOnlyUser;

  /**
   * A user with no flag permissions.
   *
   * @var AccountInterface
   */
  protected $authUser;

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();

    // Create the flag.
    $this->flag = $this->createFlag();

    // Create the full permission flag user.
    $this->fullFlagUser = $this->drupalCreateUser([
      'flag ' . $this->flag->id(),
      'unflag ' . $this->flag->id(),
    ]);

    // Create the flag only user.
    $this->flagOnlyUser = $this->drupalCreateUser([
      'flag ' . $this->flag->id(),
    ]);

    // Create a user with no flag permissions.
    $this->authUser = $this->createUser();

    // Create a node to test.
    $this->node = $this->drupalCreateNode(['type' => $this->nodeType]);
  }

  /**
   * Test permissions.
   */
  public function testPermissions() {
    // Check the full flag permission user can flag...
    $this->drupalLogin($this->fullFlagUser);
    $this->drupalGet('node/' . $this->node->id());
    $this->assertLink($this->flag->getShortText('flag'));
    $this->clickLink($this->flag->getShortText('flag'));
    $this->assertResponse(200);

    // ...and also unflag.
    $this->drupalGet('node/' . $this->node->id());
    $this->assertResponse(200);
    $this->assertLink($this->flag->getShortText('unflag'));

    // Check the flag only user can flag...
    $this->drupalLogin($this->flagOnlyUser);
    $this->drupalGet('node/' . $this->node->id());
    $this->assertLink($this->flag->getShortText('flag'));
    $this->clickLink($this->flag->getShortText('flag'));
    $this->assertResponse(200);

    // ...but not unflag.
    $this->drupalGet('node/' . $this->node->id());
    $this->assertResponse(200);
    $this->assertNoLink($this->flag->getShortText('flag'));
    $this->assertNoLink($this->flag->getShortText('unflag'));

    // Check an unprivileged authenticated user.
    $this->drupalLogin($this->authUser);
    $this->drupalGet('node/' . $this->node->id());
    $this->assertNoLink($this->flag->getShortText('flag'));

    // Check the anonymous user.
    $this->drupalLogout();
    $this->drupalGet('node/' . $this->node->id());
    $this->assertNoLink($this->flag->getShortText('flag'));
  }

}
