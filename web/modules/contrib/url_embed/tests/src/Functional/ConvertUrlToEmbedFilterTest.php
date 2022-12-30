<?php

namespace Drupal\Tests\url_embed\Functional;

use Drupal\editor\Entity\Editor;
use Drupal\filter\Entity\FilterFormat;
use Drupal\Tests\BrowserTestBase;

/**
 * Tests the url_embed_convert_links filter.
 *
 * @group url_embed
 */
class ConvertUrlToEmbedFilterTest extends BrowserTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = ['url_embed', 'node', 'ckeditor'];

  /**
   * {@inheritdoc}
   */
  public $defaultTheme = 'stark';

  /**
   * Set the configuration up.
   */
  protected function setUp() {
    parent::setUp();
    // Create a page content type.
    $this->drupalCreateContentType(['type' => 'page', 'name' => 'Basic page']);

    // Create a text format and enable the url_embed filter.
    $format = FilterFormat::create([
      'format' => 'custom_format',
      'name' => 'Custom format',
      'filters' => [
        'url_embed_convert_links' => [
          'status' => 1,
          'settings' => ['url_prefix' => ''],
        ],
      ],
    ]);
    $format->save();

    $editor_group = [
      'name' => 'URL Embed',
      'items' => [
        'url',
      ],
    ];
    $editor = Editor::create([
      'format' => 'custom_format',
      'editor' => 'ckeditor',
      'settings' => [
        'toolbar' => [
          'rows' => [[$editor_group]],
        ],
      ],
    ]);
    $editor->save();

    // Create a user with required permissions.
    $this->webUser = $this->drupalCreateUser([
      'access content',
      'create page content',
      'use text format custom_format',
    ]);
    $this->drupalLogin($this->webUser);
  }

  /**
   * Tests the url_embed_convert_links filter.
   *
   * Ensures that iframes are getting rendered when valid urls
   * are passed. Also tests situations when embed fails.
   */
  public function testFilter() {
    $content = 'before https://twitter.com/drupal/status/735873777683320832 after';
    $settings = [];
    $settings['type'] = 'page';
    $settings['title'] = 'Test convert url to embed with sample Twitter url';
    $settings['body'] = [['value' => $content, 'format' => 'custom_format']];
    $node = $this->drupalCreateNode($settings);
    $this->drupalGet('node/' . $node->id());
    $this->assertSession()->responseContains('<drupal-url data-embed-url="https://twitter.com/drupal/status/735873777683320832"></drupal-url>');
    $this->assertNoText(strip_tags($content), 'URL does not appear in the output when embed is successful.');

    $content = 'before /not-valid/url after';
    $settings = [];
    $settings['type'] = 'page';
    $settings['title'] = 'Test convert url to embed with non valid URL';
    $settings['body'] = [['value' => $content, 'format' => 'custom_format']];
    $node = $this->drupalCreateNode($settings);
    $this->drupalGet('node/' . $node->id());
    $this->assertSession()->responseContains($content);

    /** @var \Drupal\filter\FilterFormatInterface $format */
    $format = FilterFormat::load('custom_format');
    $configuration = $format->filters('url_embed_convert_links')->getConfiguration();
    $configuration['settings']['url_prefix'] = 'EMBED ';
    $format->setFilterConfig('url_embed_convert_links', $configuration);
    $format->save();

    $content = 'before https://twitter.com/drupal/status/735873777683320832 after';
    $settings = [];
    $settings['type'] = 'page';
    $settings['title'] = 'Test convert url to embed with sample Twitter url and no prefix';
    $settings['body'] = [['value' => $content, 'format' => 'custom_format']];
    $node = $this->drupalCreateNode($settings);
    $this->drupalGet('node/' . $node->id());
    $this->assertSession()->responseContains(strip_tags($content));
    $this->assertSession()->responseNotContains('<drupal-url data-embed-url="https://twitter.com/drupal/status/735873777683320832"></drupal-url>');

    $content = 'before EMBED https://twitter.com/drupal/status/735873777683320832 after';
    $settings = [];
    $settings['type'] = 'page';
    $settings['title'] = 'Test convert url to embed with sample Twitter url with the prefix';
    $settings['body'] = [['value' => $content, 'format' => 'custom_format']];
    $node = $this->drupalCreateNode($settings);
    $this->drupalGet('node/' . $node->id());
    $this->assertSession()->responseContains('<drupal-url data-embed-url="https://twitter.com/drupal/status/735873777683320832"></drupal-url>');
    $this->assertNoText(strip_tags($content), 'URL does not appear in the output when embed is successful.');

    $content = 'before Embed https://twitter.com/drupal/status/735873777683320832 after';
    $settings = [];
    $settings['type'] = 'page';
    $settings['title'] = 'Test convert url to embed with sample Twitter url with wrong prefix';
    $settings['body'] = [['value' => $content, 'format' => 'custom_format']];
    $node = $this->drupalCreateNode($settings);
    $this->drupalGet('node/' . $node->id());
    $this->assertSession()->responseContains(strip_tags($content));
    $this->assertSession()->responseNotContains('<drupal-url data-embed-url="https://twitter.com/drupal/status/735873777683320832"></drupal-url>');
  }

}
