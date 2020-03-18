<?php

/**
 * @file
 * Contains \Drupal\url_embed\Tests\LinkEmbedFormatterTest.
 */

namespace Drupal\url_embed\Tests;

use Drupal\Component\Utility\Unicode;
use Drupal\entity_test\Entity\EntityTest;
use Drupal\link\LinkItemInterface;
use Drupal\link\Tests\LinkFieldTest;
use Drupal\url_embed\Tests\UrlEmbedTestBase;

/**
 * Tests url_embed link field formatter.
 *
 * @group url_embed
 */
class LinkEmbedFormatterTest extends LinkFieldTest{

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = array('url_embed');

  /**
   * Tests the 'url_embed' formatter.
   */
  function testLinkEmbedFormatter() {
    $field_name = Unicode::strtolower($this->randomMachineName());
    // Create a field with settings to validate.
    $this->fieldStorage = entity_create('field_storage_config', array(
      'field_name' => $field_name,
      'entity_type' => 'entity_test',
      'type' => 'link',
      'cardinality' => 2,
    ));
    $this->fieldStorage->save();
    entity_create('field_config', array(
      'field_storage' => $this->fieldStorage,
      'bundle' => 'entity_test',
      'settings' => array(
        'title' => DRUPAL_OPTIONAL,
        'link_type' => LinkItemInterface::LINK_GENERIC,
      ),
    ))->save();
    entity_get_form_display('entity_test', 'entity_test', 'default')
      ->setComponent($field_name, array(
        'type' => 'link_default',
      ))
      ->save();
    $display_options = array(
      'type' => 'url_embed',
      'label' => 'hidden',
    );
    entity_get_display('entity_test', 'entity_test', 'full')
      ->setComponent($field_name, $display_options)
      ->save();

    // Create an entity to test the embed formatter.
    $url = UrlEmbedTestBase::FLICKR_URL;
    $entity = EntityTest::create();
    $entity->set($field_name, $url);
    $entity->save();

    // Render the entity and verify that the link is output as an embed.
    $this->renderTestEntity($entity->id());
    $this->assertRaw(UrlEmbedTestBase::FLICKR_OUTPUT_FIELD);
  }
}
