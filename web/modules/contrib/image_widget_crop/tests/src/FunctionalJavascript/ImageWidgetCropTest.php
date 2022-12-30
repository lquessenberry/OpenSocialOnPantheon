<?php

namespace Drupal\Tests\image_widget_crop\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\node\Entity\Node;
use Drupal\Tests\TestFileCreationTrait;

/**
 * Minimal test case for the image_widget_crop module.
 *
 * @group image_widget_crop
 *
 * @ingroup media
 */
class ImageWidgetCropTest extends WebDriverTestBase {

  use TestFileCreationTrait {
    getTestFiles as drupalGetTestFiles;
  }

  /**
   * User with permissions to create content.
   *
   * @var \Drupal\user\Entity\User
   */
  protected $user;

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = [
    'node',
    'crop',
    'image',
    'image_widget_crop',
  ];

  /**
   * Prepares environment for the tests.
   */
  protected function setUp() {
    parent::setUp();

    $this->drupalCreateContentType(['name' => 'Crop test', 'type' => 'crop_test']);

    $this->user = $this->createUser([
      'access content overview',
      'administer content types',
      'edit any crop_test content',
    ]);
    $this->drupalLogin($this->user);
  }

  /**
   * Test Image Widget Crop UI.
   */
  public function testCropUi() {
    // Test that when a crop has more than one usage we have a warning.
    $this->createImageField('field_image_crop_test', 'crop_test', 'image_widget_crop', [], [], ['crop_list' => ['crop_16_9' => 'crop_16_9'], 'crop_types_required' => []]);
    $this->drupalGetTestFiles('image');

    $this->drupalGet('node/add/crop_test');
    $edit = [
      'title[0][value]' => $this->randomMachineName(),
    ];
    $this->getSession()->getPage()->attachFileToField('files[field_image_crop_test_0]', $this->container->get('file_system')->realpath('public://image-test.jpg'));
    $this->drupalPostForm(NULL, $edit, 'Save');

    $node = Node::create([
      'title' => '2nd node using it',
      'type' => 'crop_test',
      'field_image_crop_test' => 1,
      'alt' => $this->randomMachineName(),
    ]);
    $node->save();

    /** @var \Drupal\file\FileUsage\FileUsageInterface $usage */
    $usage = \Drupal::service('file.usage');
    $usage->add(\Drupal::service('entity_type.manager')->getStorage('file')->load(1), 'image_widget_crop', 'node', $node->id());

    $this->drupalGet('node/1/edit');
    $this->assertSession()->responseContains(t('This crop definition affects more usages of this image'));

  }

  /**
   * Test Image Widget Crop.
   */
  public function testImageWidgetCrop() {
    // Test that crop widget works properly.
    $this->createImageField('field_image_crop_test', 'crop_test', 'image_widget_crop', [], [], ['crop_list' => ['crop_16_9' => 'crop_16_9'], 'crop_types_required' => []]);
    $this->drupalGetTestFiles('image');

    $this->drupalGet('node/add/crop_test');

    // Assert that there is no crop widget, neither 'Alternative text' text
    // filed nor 'Remove' button yet.
    $assert_session = $this->assertSession();
    $assert_session->elementNotExists('css', 'summary:contains(Crop image)');
    $assert_session->pageTextNotContains('Alternative text');
    $assert_session->fieldNotExists('field_image_crop_test_0_remove_button');

    // Upload an image in field_image_crop_test_0.
    $this->getSession()->getPage()->attachFileToField('files[field_image_crop_test_0]', $this->container->get('file_system')->realpath('public://image-test.jpg'));

    // Assert that now crop widget and 'Alternative text' text field appear and
    // that 'Remove' button exists.
    $assert_session->assertWaitOnAjaxRequest();
    $assert_session->elementExists('css', 'summary:contains(Crop image)');
    $assert_session->pageTextContains('Alternative text');
    $assert_session->buttonExists('Remove');

    // Set title and 'Alternative text' text field and save.
    $title = $this->randomMachineName();
    $edit = [
      'title[0][value]' => $title,
      'field_image_crop_test[0][alt]' => $this->randomMachineName(),
    ];
    $this->drupalPostForm(NULL, $edit, 'Save');
    $assert_session->pageTextContains('Crop test ' . $title . ' has been created.');
    $url = $this->getUrl();
    $nid = substr($url, -1, strrpos($url, '/'));

    // Edit crop image.
    $this->drupalGet('node/' . $nid . '/edit');

    // Verify that the 'Remove' button works properly.
    $assert_session->pageTextContains('Alternative text');
    $this->getSession()->getPage()->pressButton('Remove');
    $assert_session->assertWaitOnAjaxRequest();
    $assert_session->pageTextNotContains('Alternative text');

    $this->getSession()->getPage()->attachFileToField('files[field_image_crop_test_0]', $this->container->get('file_system')->realpath('public://image-test.jpg'));

    // Verify that the 'Preview' button works properly.
    $this->drupalPostForm(NULL, $edit, 'Preview');
    $assert_session->linkExists('Back to content editing');
    $this->clickLink('Back to content editing');

    // Verify that there is an image style preview.
    $assert_session->hiddenFieldValueEquals('field_image_crop_test[0][width]', '40');
    $assert_session->hiddenFieldValueEquals('field_image_crop_test[0][height]', '20');
  }

  /**
   * Gets IEF button name.
   *
   * @param string $xpath
   *   Xpath of the button.
   *
   * @return string
   *   The name of the button.
   */
  protected function getButtonName($xpath) {
    $retval = '';

    /** @var \SimpleXMLElement[] $elements */
    if ($elements = $this->xpath($xpath)) {
      foreach ($elements[0]->attributes() as $name => $value) {
        if ($name == 'name') {
          $retval = (string) $value;
          break;
        }
      }
    }
    return $retval;
  }

  /**
   * Create a new image field.
   *
   * @param string $name
   *   The name of the new field (all lowercase), exclude the "field_" prefix.
   * @param string $type_name
   *   The node type that this field will be added to.
   * @param string $widget_name
   *   The name of the widget.
   * @param array $storage_settings
   *   A list of field storage settings that will be added to the defaults.
   * @param array $field_settings
   *   A list of instance settings that will be added to the instance defaults.
   * @param array $widget_settings
   *   A list of widget settings that will be added to the widget defaults.
   */
  protected function createImageField($name, $type_name, $widget_name, array $storage_settings = [], array $field_settings = [], array $widget_settings = []) {
    \Drupal::entityTypeManager()->getStorage('field_storage_config')->create([
      'field_name' => $name,
      'entity_type' => 'node',
      'type' => 'image',
      'settings' => $storage_settings,
      'cardinality' => !empty($storage_settings['cardinality']) ? $storage_settings['cardinality'] : 1,
    ])->save();

    $field_config = \Drupal::entityTypeManager()->getStorage('field_config')->create([
      'field_name' => $name,
      'label' => $name,
      'entity_type' => 'node',
      'bundle' => $type_name,
      'required' => !empty($field_settings['required']),
      'settings' => $field_settings,
    ]);
    $field_config->save();

    /** @var \Drupal\Core\Entity\Display\EntityFormDisplayInterface $form_display */
    $form_display = \Drupal::entityTypeManager()->getStorage('entity_form_display')->load('node.' . $type_name . '.default');
    $form_display->setComponent($name, [
      'type' => $widget_name,
      'settings' => $widget_settings,
    ])->save();

    /** @var \Drupal\Core\Entity\Display\EntityViewDisplayInterface $view_display */
    $view_display = \Drupal::entityTypeManager()->getStorage('entity_view_display')->load('node.' . $type_name . '.default');
    $view_display->setComponent($name)
      ->save();

  }

}
