<?php

namespace Drupal\Tests\block_field\Functional;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\node\Entity\Node;
use Drupal\Tests\BrowserTestBase;
use Drupal\user\Entity\User;

/**
 * Test the video embed field widget.
 *
 * @group block_field
 */
class WidgetTest extends BrowserTestBase {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'block_field_widget_test',
  ];

  /** @var User */
  private $adminUser;

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();

    $this->adminUser = $this->createAdminUser();
    $this->drupalLogin($this->adminUser);
  }

  /**
   * @test
   */
  public function blockSettingsAreStoredCorrectly() {
    $nodes = $this->createDummyNodes('item', 5);
    $this->drupalPostForm('node/add/block_node', [
      'title[0][value]' => 'Block field test',
      'field_block[0][plugin_id]' => 'views_block:items-block_1',
      ], $this->t('Save and publish'));

    $this->drupalGet('node/6/edit');
    $this->submitForm([
      'field_block[0][settings][override][items_per_page]' => 5,
    ], $this->t('Save and keep published'));

    do {
      $this->assertSession()->pageTextContains(array_pop($nodes)->getTitle());
    }
    while (count($nodes));
  }

  /**
   * @return User|false
   */
  private function createAdminUser() {
    return $this->drupalCreateUser(array_keys($this->container->get('user.permissions')->getPermissions()));
  }

  /**
   * @param string $contentType
   * @param int $numberOfNodes
   * @return Node[]
   */
  private function createDummyNodes($contentType, $numberOfNodes) {
    $this->assertGreaterThan(0, $numberOfNodes);

    $nodes = [];

    do {
      $nodes[] = $this->createNode(['type' => $contentType]);
    }
    while (count($nodes) < $numberOfNodes);

    return $nodes;
  }

}
