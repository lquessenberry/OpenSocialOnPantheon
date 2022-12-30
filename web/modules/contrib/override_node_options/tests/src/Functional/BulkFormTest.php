<?php

namespace Drupal\Tests\override_node_options\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Functional test for override_node_options bulk form operations.
 *
 * @group override_node_options
 */
class BulkFormTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'action_bulk_test',
    'node',
    'override_node_options',
  ];

  /**
   * Test nodes can be bulk-unpublished.
   */
  public function testUnpublishAction() {
    $this->drupalCreateContentType(['type' => 'article']);

    $account = $this->drupalCreateUser([
      'access content overview',
      'create article content',
      'edit any article content',
      'override article published option',
    ]);

    $this->drupalCreateNode(['type' => 'article']);
    $articleB = $this->drupalCreateNode(['type' => 'article']);
    $this->drupalCreateNode(['type' => 'article']);

    $this->drupalLogin($account);

    $this->drupalGet('test_bulk_form');

    // Ensure that we can see the content listing.
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains($articleB->label());

    $edit = [
      'action' => 'node_unpublish_action',
      'node_bulk_form[0]' => TRUE,
    ];

    $this->drupalPostForm(NULL, $edit, t('Apply to selected items'));

    $this->assertSession()->pageTextContains('Unpublish content was applied to 1 item.');
    $this->assertSession()->pageTextNotContains('No access to execute Unpublish content');
  }

}
