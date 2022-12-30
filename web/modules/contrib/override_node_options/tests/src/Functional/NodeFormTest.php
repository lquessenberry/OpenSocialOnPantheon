<?php

namespace Drupal\Tests\override_node_options\Functional;

use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\node\NodeInterface;
use Drupal\Tests\BrowserTestBase;

/**
 * Unit tests for the override_node_options module.
 *
 * @group override_node_options
 */
class NodeFormTest extends BrowserTestBase {

  /**
   * A standard authenticated user.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $normalUser;

  /**
   * A node to test against.
   *
   * @var \Drupal\node\NodeInterface
   */
  protected $node;

  /**
   * {@inheritdoc}
   */
  public static $modules = ['override_node_options'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();

    $types = NodeType::loadMultiple();
    if (empty($types['article'])) {
      $this->drupalCreateContentType(['type' => 'page', 'name' => t('Page')]);
    }

    $this->normalUser = $this->drupalCreateUser([
      'create page content',
      'edit any page content',
    ]);

    $this->node = $this->drupalCreateNode();
  }

  /**
   * Assert that fields in a node were updated to certain values.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node object to check (will be reloaded from the database).
   * @param array $fields
   *   An array of values to check equality, keyed by node object property.
   * @param int $vid
   *   The node revision ID to load.
   */
  public function assertNodeFieldsUpdated(NodeInterface $node, array $fields, $vid = NULL) {
    if (!$vid) {
      // Re-load the node from the database to make sure we have the current
      // values.
      $node = Node::load($node->id());
    }

    if ($vid) {
      $node = node_revision_load($vid);
    }

    foreach ($fields as $field => $value) {
      self::assertEquals(
        $node->get($field)->value,
        $value,
        t('Node :field was updated to :value, expected :expected.',
          [
            ':field' => $field,
            ':value' => $node->get($field)->value,
            ':expected' => $value,
          ]
        )
      );
    }
  }

  /**
   * Assert that the user cannot access fields on node add and edit forms.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node object, will be used on the node edit form.
   * @param array $fields
   *   An array of form fields to check.
   */
  public function assertNodeFieldsNoAccess(NodeInterface $node, array $fields) {
    $this->drupalGet('node/add/' . $node->getType());
    foreach ($fields as $field) {
      $this->assertSession()->fieldNotExists($field);
    }

    $this->drupalGet('node/' . $this->node->id() . '/edit');
    foreach ($fields as $field) {
      $this->assertSession()->fieldNotExists($field);
    }
  }

  /**
   * Test the 'Authoring information' fieldset.
   */
  public function testNodeOptions() {
    $specific_user = $this->drupalCreateUser([
      'create page content',
      'edit any page content',
      'override page published option',
      'override page promote to front page option',
      'override page sticky option',
    ]);

    $general_user = $this->drupalCreateUser([
      'create page content',
      'edit any page content',
      'override all published option',
      'override all promote to front page option',
      'override all sticky option',
    ]);

    $fields = [
      'promote' => TRUE,
      'status' => TRUE,
      'sticky' => TRUE,
    ];

    foreach ([$specific_user, $general_user] as $user) {
      $this->drupalLogin($user);

      $this->drupalPostForm(
        "node/{$this->node->id()}/edit",
        [
          'promote[value]' => TRUE,
          'status[value]' => TRUE,
          'sticky[value]' => TRUE,
        ],
        t('Save')
      );

      $this->assertNodeFieldsUpdated($this->node, $fields);
    }

    $this->drupalLogin($this->normalUser);

    $this->assertNodeFieldsNoAccess($this->node, array_keys($fields));
  }

  /**
   * Test the 'Revision information' fieldset.
   */
  public function testNodeRevisions() {
    $specific_user = $this->drupalCreateUser([
      'create page content',
      'edit any page content',
      'enter page revision log entry',
      'override page revision option',
    ]);

    $general_user = $this->drupalCreateUser([
      'create page content',
      'edit any page content',
      'enter all revision log entry',
      'override all revision option',
    ]);

    $fields = [
      'revision' => TRUE,
      'revision_log' => TRUE,
    ];

    foreach ([$specific_user, $general_user] as $user) {
      $this->drupalLogin($user);

      $this->drupalPostForm('node/' . $this->node->id() . '/edit', [
        'revision' => TRUE,
        'revision_log[0][value]' => '',
      ], t('Save'));

      $this->assertNodeFieldsUpdated($this->node, [], $this->node->getRevisionId());
    }

    $this->drupalLogin($this->normalUser);

    $this->assertNodeFieldsNoAccess($this->node, array_keys($fields));
  }

  /**
   * Test the 'Authoring information' fieldset.
   */
  public function testNodeAuthor() {
    $specific_user = $this->drupalCreateUser([
      'create page content',
      'edit any page content',
      'override page authored on option',
      'override page authored by option',
    ]);

    $general_user = $this->drupalCreateUser([
      'create page content',
      'edit any page content',
      'override all authored by option',
      'override all authored on option',
    ]);

    $time = \Drupal::time()->getCurrentTime();
    $formatter = \Drupal::service('date.formatter');

    $fields = [
      'created[0][value][date]' => $formatter->format($time, 'custom', 'Y-m-d'),
      'created[0][value][time]' => $formatter->format($time, 'custom', 'H:i:s'),
      'uid[0][target_id]' => '',
    ];

    foreach ([$specific_user, $general_user] as $user) {
      $this->drupalLogin($user);

      $this->drupalPostForm('node/' . $this->node->id() . '/edit', ['uid[0][target_id]' => 'invalid-user'], t('Save'));

      $this->assertSession()->pageTextContains('There are no entities matching "invalid-user".');

      $this->drupalPostForm('node/' . $this->node->id() . '/edit', ['created[0][value][date]' => 'invalid-date'], t('Save'));

      $this->assertSession()->pageTextContains('The Authored on date is invalid.');

      $this->drupalPostForm('node/' . $this->node->id() . '/edit', $fields, t('Save'));

      $this->assertNodeFieldsUpdated($this->node, [
        'created' => $time,
        'uid' => 0,
      ]);
    }

    $this->drupalLogin($this->normalUser);

    $this->assertNodeFieldsNoAccess($this->node, array_keys($fields));
  }

}
