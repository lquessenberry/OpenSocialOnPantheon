<?php

namespace Drupal\flag_bookmark\Tests;

use Drupal\simpletest\WebTestBase;

/**
 * UI Test for flag_bookmark.
 *
 * @group flag_bookmark
 */
class FlagBookmarkUITest extends WebTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = [
    'views',
    'flag',
    'flag_bookmark',
  ];

  /**
   * Administrator user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $adminUser;

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();

    $this->drupalCreateContentType(['type' => 'article', 'name' => 'Article']);

    // Create a test user and log in.
    $this->adminUser = $this->drupalCreateUser(array(
      'flag bookmark',
      'unflag bookmark',
      'create article content',
      'access content overview',
    ));
    $this->drupalLogin($this->adminUser);
  }

  /**
   * Test the flag_bookmark UI.
   */
  public function testUi() {

    // Add articles.
    $this->drupalPostForm('node/add/article', [
      'title[0][value]' => 'Article 1',
    ], t('Save'));

    $auth_user = $this->drupalCreateUser([
      'flag bookmark',
      'unflag bookmark',
    ]);
    $this->drupalLogin($auth_user);

    // Check the link to bookmark exist.
    $this->drupalGet('node/1');
    $this->assertLink(t('Bookmark this'));

    // Bookmark article.
    $this->clickLink(t('Bookmark this'));

    // Check if the bookmark appears in the frontpage.
    $this->drupalGet('node');
    $this->assertLink(t('Remove bookmark'));

    // Check the view is shown correctly.
    $this->drupalGet('bookmarks');
    $xpath = $this->xpath('/html/body/div/main/div/div/div/div/div/table/tbody/tr/td[1]');
    $this->assertEqual($xpath[0]->a, 'Article');
  }

}
