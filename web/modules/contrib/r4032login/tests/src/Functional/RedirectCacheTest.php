<?php

namespace Drupal\Tests\r4032login\Functional;

use Drupal\file\Entity\File;
use Drupal\node\Entity\NodeType;
use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\file\Functional\FileFieldCreationTrait;

/**
 * Test caching redirection.
 *
 * @group r4032login
 */
class RedirectCacheTest extends BrowserTestBase {

  use FileFieldCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'file',
    'node',
    'r4032login',
  ];

  /**
   * An unpublished node used for tests.
   *
   * @var \Drupal\node\NodeInterface
   */
  protected $unpublishedNode;

  /**
   * An published node used for tests.
   *
   * @var \Drupal\node\NodeInterface
   */
  protected $publishedNode;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Disable the access denied message so the cache will be set.
    $config = $this->config('r4032login.settings');
    $config->set('display_denied_message', FALSE);
    $config->save();

    // Create a node type with a private file field.
    $nodeType = NodeType::create(['type' => 'page', 'name' => 'Basic page']);
    $nodeType->save();
    $this->createFileField('field_text_file', 'node', 'page', ['uri_scheme' => 'private']);

    // Create an unpublished node with a private file to test.
    $this->unpublishedNode = $this->drupalCreateNode();
    file_put_contents('private://test.txt', 'test');
    $file = File::create([
      'uri' => 'private://test.txt',
      'filename' => 'test.txt',
    ]);
    $file->save();
    $this->unpublishedNode->set('field_text_file', $file->id());
    $this->unpublishedNode->setUnpublished()->save();

    // Create a published node with a private file to test.
    $this->publishedNode = $this->drupalCreateNode();
    file_put_contents('private://test2.txt', 'test2');
    $file = File::create([
      'uri' => 'private://test2.txt',
      'filename' => 'test2.txt',
    ]);
    $file->save();
    $this->publishedNode->set('field_text_file', $file->id());
    $this->publishedNode->setPublished()->save();
  }

  /**
   * Test node access redirect behavior in cached context.
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   */
  public function testNodeRedirectCache() {
    // Assert there is the redirection since the node is not published.
    $this->drupalGet('node/' . $this->unpublishedNode->id());
    $this->assertSession()->addressEquals('user/login');

    // Publish the node.
    $this->unpublishedNode->setPublished()->save();
    $newlyPublishedNode = $this->unpublishedNode;

    // Assert there is not the redirection since the node is published.
    $this->drupalGet('node/' . $newlyPublishedNode->id());
    $this->assertSession()->addressEquals('node/' . $newlyPublishedNode->id());
  }

  /**
   * Tests private file redirect behavior in cached context.
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   */
  public function testPrivateFileRedirectCache() {
    $fileUrlGenerator = \Drupal::service('file_url_generator');

    // Assert there is the redirection since the node is not published.
    $this->drupalGet($fileUrlGenerator->generateAbsoluteString($this->unpublishedNode->field_text_file->entity->getFileUri()));
    $this->assertSession()->addressEquals('user/login');

    // Assert there is not the redirection for an already published node file.
    $this->drupalGet($fileUrlGenerator->generateAbsoluteString($this->publishedNode->field_text_file->entity->getFileUri()));
    $this->assertSession()->addressEquals($fileUrlGenerator->generateAbsoluteString($this->publishedNode->field_text_file->entity->getFileUri()));

    // Publish the node.
    $this->unpublishedNode->setPublished()->save();
    $newlyPublishedNode = $this->unpublishedNode;

    // Assert there is not the redirection since the node is now published.
    $this->drupalGet($fileUrlGenerator->generateAbsoluteString($newlyPublishedNode->field_text_file->entity->getFileUri()));
    $this->assertSession()->addressEquals($fileUrlGenerator->generateAbsoluteString($newlyPublishedNode->field_text_file->entity->getFileUri()));
  }

}
