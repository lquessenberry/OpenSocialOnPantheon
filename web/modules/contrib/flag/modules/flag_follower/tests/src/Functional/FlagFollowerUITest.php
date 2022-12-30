<?php

namespace Drupal\Tests\flag_follower\Functional;

use Drupal\Tests\BrowserTestBase;
/**
 * UI Test for flag_follower.
 *
 * @group flag_follower
 */
class FlagFollowerUITest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = [
    'views',
    'flag',
    'flag_follower',
    'user',
    'node',
  ];

  /**
   * Administrator user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $adminUser;

  /**
   * User A.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $userA;

  /**
   * User B.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $userB;

  /**
   * User C.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $userC;

  /**
   * Node A created by User A.
   *
   * @var \Drupal\Node\NodeInterface
   */
  protected $nodeA;

  /**
   * Node B created by User B.
   *
   * @var \Drupal\Node\NodeInterface
   */
  protected $nodeB;

  /**
   * Node C created by User C.
   *
   * @var \Drupal\Node\NodeInterface
   */
  protected $nodeC;

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();

    $this->drupalCreateContentType(['type' => 'article', 'name' => 'Article']);

    // Create a test user and log in.
    $this->adminUser = $this->drupalCreateUser([
      'flag following',
      'unflag following',
      'create article content',
      'access content overview',
    ]);
    $this->drupalLogin($this->adminUser);

    // Create user A.
    $this->userA = $this->drupalCreateUser([
      'flag following',
      'unflag following',
      'create article content',
      'access user profiles',
    ]);

    // Create user B.
    $this->userB = $this->drupalCreateUser([
      'flag following',
      'unflag following',
      'create article content',
      'access user profiles',
    ]);

    // Create user C.
    $this->userC = $this->drupalCreateUser([
      'flag following',
      'unflag following',
      'create article content',
      'access user profiles',
    ]);

    // Create user A's node.
    $this->drupalLogin($this->userA);
    $this->nodeA = $this->drupalCreateNode(['type' => 'article']);

    // Create user B's node.
    $this->drupalLogin($this->userB);
    $this->nodeB = $this->drupalCreateNode(['type' => 'article']);

    // Create user C's node.
    $this->drupalLogin($this->userC);
    $this->nodeC = $this->drupalCreateNode(['type' => 'article']);
  }

  /**
   * Perform all fallover tests.
   */
  public function testUi() {
    $this->doUserFlagging();
    $this->doFollowerView();
    $this->doContentView();
  }

  /**
   * Test user flagging.
   *
   * User A follows user C. B follows A and C. C follows no one.
   */
  public function doUserFlagging() {
    // User A follows user C.
    $this->drupalLogin($this->userA);
    $this->drupalGet('user/' . $this->userC->id());
    $this->clickLink(t('Follow this user'));

    // User B follows user A.
    $this->drupalLogin($this->userB);
    $this->drupalGet('user/' . $this->userA->id());
    $this->clickLink(t('Follow this user'));

    // User B also follows user C.
    $this->drupalGet('user/' . $this->userC->id());
    $this->clickLink(t('Follow this user'));

    // User C follows no one.
  }

  /**
   * Test the flag relationship.
   */
  public function doFollowerView() {
    $this->drupalLogin($this->userA);
    $this->drupalGet('flag-followers');
    $this->assertNoText($this->userB->getAccountName());
    $this->assertText($this->userC->getAccountName());
    $this->assertText('2', 'A sees C has two followers.');

    $this->drupalLogin($this->userB);
    $this->drupalGet('flag-followers');
    $this->assertText($this->userA->getAccountName());
    $this->assertText($this->userC->getAccountName());
    $this->assertText('2', 'B sees C has two followers.');
    $this->assertText('1', 'B sees A has one follower.');

    $this->drupalLogin($this->userC);
    $this->drupalGet('flag-followers');
    $this->assertNoText($this->userA->getAccountName());
    $this->assertNoText($this->userB->getAccountName());
  }

  /**
   * Test the flag relationship on another relationship.
   */
  public function doContentView() {
    $this->drupalLogin($this->userA);
    $this->drupalGet('flag-followers/content');
    $this->assertText($this->nodeC->label());
    $this->assertNoText($this->nodeB->label());

    $this->drupalLogin($this->userB);
    $this->drupalGet('flag-followers/content');
    $this->assertText($this->nodeA->label());
    $this->assertText($this->nodeC->label());

    $this->drupalLogin($this->userC);
    $this->drupalGet('flag-followers/content');
    $this->assertNoText($this->nodeA->label());
    $this->assertNoText($this->nodeB->label());
  }

}
