<?php

namespace Drupal\Tests\select2\FunctionalJavascript\FieldWidget;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Url;
use Drupal\entity_test\Entity\EntityTestBundle;
use Drupal\entity_test\Entity\EntityTestMulRevPub;
use Drupal\entity_test\Entity\EntityTestWithBundle;
use Drupal\field\Entity\FieldConfig;
use Drupal\Tests\select2\FunctionalJavascript\Select2JavascriptTestBase;
use Drupal\Tests\TestFileCreationTrait;

/**
 * Tests select2 entity reference widget.
 *
 * @group select2
 */
class Select2EntityReferenceWidgetTest extends Select2JavascriptTestBase {

  use TestFileCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['entity_test'];

  /**
   * Test a single value widget.
   *
   * @dataProvider providerTestSingleValueWidget
   */
  public function testSingleValueWidget($autocomplete, $match_operator, $count, $autocreate) {
    $this->createField('select2', 'node', 'test', 'entity_reference', [
      'target_type' => 'entity_test_mulrevpub',
    ], [
      'handler' => 'default:entity_test_mulrevpub',
      'handler_settings' => [
        'auto_create' => $autocreate,
      ],
    ], 'select2_entity_reference', [
      'autocomplete' => $autocomplete,
      'match_operator' => $match_operator,
      'match_limit' => 10,
    ]);

    EntityTestMulRevPub::create(['name' => 'foo'])->save();
    EntityTestMulRevPub::create(['name' => 'bar'])->save();
    EntityTestMulRevPub::create(['name' => 'bar foo'])->save();
    EntityTestMulRevPub::create(['name' => 'gaga'])->save();

    $page = $this->getSession()->getPage();
    $assert_session = $this->assertSession();

    $this->drupalGet('/node/add/test');
    $page->fillField('title[0][value]', 'Test node');
    $this->click('.form-item-select2 .select2-selection.select2-selection--single');

    $page->find('css', '.select2-search__field')->setValue('fo');
    $assert_session->waitForElement('xpath', '//li[contains(@class, "select2-results__option") and text()="foo"]');
    $assert_session->elementsCount('xpath', '//li[contains(@class, "select2-results__option")]', $count);

    $page->find('xpath', '//li[contains(@class, "select2-results__option") and text()="foo"]')->click();
    $page->pressButton('Save');

    $node = $this->getNodeByTitle('Test node', TRUE);
    $this->assertEquals([['target_id' => 1]], $node->select2->getValue());

    if ($autocreate) {
      $this->drupalGet($node->toUrl('edit-form'));
      $this->click('.form-item-select2 .select2-selection.select2-selection--single');
      $page->find('css', '.select2-search__field')->setValue('Preview value');
      $assert_session->waitForElement('css', '.select2-results__option--highlighted');
      $page->find('css', '.select2-results__option--highlighted')->click();

      $page->pressButton('Preview');
      $page->clickLink('Back to content editing');
      $page->pressButton('Save');

      $node = $this->getNodeByTitle('Test node', TRUE);
      $this->assertEquals([['target_id' => 5]], $node->select2->getValue());
      $this->assertNotEmpty(EntityTestMulRevPub::load(5));

      $this->drupalGet($node->toUrl('edit-form'));
      $this->click('.form-item-select2 .select2-selection.select2-selection--single');
      $page->find('css', '.select2-search__field')->setValue('New value');
      $assert_session->waitForElement('css', '.select2-results__option--highlighted');
      $page->find('css', '.select2-results__option--highlighted')->click();

      $page->pressButton('Save');

      $node = $this->getNodeByTitle('Test node', TRUE);
      $this->assertEquals([['target_id' => 6]], $node->select2->getValue());
      $this->assertNotEmpty(EntityTestMulRevPub::load(6));
    }
  }

  /**
   * Data provider for testSingleValueWidget().
   *
   * @return array
   *   The data.
   */
  public function providerTestSingleValueWidget() {
    return [
      [TRUE, 'STARTS_WITH', 2, TRUE],
      [FALSE, NULL, 3, TRUE],
      [FALSE, NULL, 2, FALSE],
      [TRUE, 'STARTS_WITH', 1, FALSE],
      [TRUE, 'CONTAINS', 2, FALSE],
    ];
  }

  /**
   * Test autocomplete in a multiple value field.
   *
   * @dataProvider providerTestMultiValueWidget
   */
  public function testMultipleValueWidget($autocomplete, $autocreate) {
    $this->createField('select2', 'node', 'test', 'entity_reference', [
      'target_type' => 'entity_test_mulrevpub',
      'cardinality' => -1,
    ], [
      'handler' => 'default:entity_test_mulrevpub',
      'handler_settings' => [
        'auto_create' => $autocreate,
      ],
    ], 'select2_entity_reference', ['autocomplete' => $autocomplete]);

    EntityTestMulRevPub::create(['name' => 'foo'])->save();
    EntityTestMulRevPub::create(['name' => 'bar'])->save();
    EntityTestMulRevPub::create(['name' => 'gaga'])->save();

    $page = $this->getSession()->getPage();
    $assert_session = $this->assertSession();

    $this->drupalGet('/node/add/test');
    $page->fillField('title[0][value]', 'Test node');

    $this->click('.form-item-select2 .select2-selection.select2-selection--multiple');
    $page->find('css', '.select2-search__field')->setValue('fo');
    $assert_session->waitForElement('xpath', '//li[contains(@class, "select2-results__option") and text()="foo"]');
    $page->find('xpath', '//li[contains(@class, "select2-results__option") and text()="foo"]')->click();

    $this->click('.form-item-select2 .select2-selection.select2-selection--multiple');
    $page->find('css', '.select2-search__field')->setValue('ga');
    $assert_session->waitForElement('xpath', '//li[contains(@class, "select2-results__option") and text()="gaga"]');
    $page->find('xpath', '//li[contains(@class, "select2-results__option") and text()="gaga"]')->click();

    $page->pressButton('Save');

    $node = $this->getNodeByTitle('Test node', TRUE);
    $this->assertEquals([['target_id' => 1], ['target_id' => 3]], $node->select2->getValue());

    if ($autocreate) {
      $this->drupalGet($node->toUrl('edit-form'));
      $this->click('.form-item-select2 .select2-selection.select2-selection--multiple');
      $page->find('css', '.select2-search__field')->setValue('Preview value');
      $assert_session->waitForElement('css', '.select2-results__option--highlighted');
      $page->find('css', '.select2-results__option--highlighted')->click();

      $page->pressButton('Preview');
      $page->clickLink('Back to content editing');
      $page->pressButton('Save');

      $node = $this->getNodeByTitle('Test node', TRUE);
      $this->assertEquals([
        ['target_id' => 1],
        ['target_id' => 3],
        ['target_id' => 4],
      ], $node->select2->getValue());
      $this->assertNotEmpty(EntityTestMulRevPub::load(4));

      $this->drupalGet($node->toUrl('edit-form'));
      $this->click('.form-item-select2 .select2-selection.select2-selection--multiple');
      $page->find('css', '.select2-search__field')->setValue('New value');
      $assert_session->waitForElement('css', '.select2-results__option--highlighted');
      $page->find('css', '.select2-results__option--highlighted')->click();

      $page->pressButton('Save');

      $node = $this->getNodeByTitle('Test node', TRUE);
      $this->assertEquals([
        ['target_id' => 1],
        ['target_id' => 3],
        ['target_id' => 4],
        ['target_id' => 5],
      ], $node->select2->getValue());
      $this->assertNotEmpty(EntityTestMulRevPub::load(5));
    }
  }

  /**
   * Data provider for testMultipleValueWidget().
   *
   * @return array
   *   The data.
   */
  public function providerTestMultiValueWidget() {
    return [
      [TRUE, TRUE],
      [TRUE, FALSE],
      [FALSE, TRUE],
      [FALSE, FALSE],
    ];
  }

  /**
   * Test autocreation for a multi value field.
   */
  public function testMultipleAutocreation() {
    EntityTestBundle::create([
      'id' => 'test1',
      'label' => 'Test1 label',
      'description' => 'My test description',
    ])->save();

    EntityTestBundle::create([
      'id' => 'test2',
      'label' => 'Test2 label',
      'description' => 'My test description',
    ])->save();

    $this->createField('select2', 'node', 'test', 'entity_reference', [
      'target_type' => 'entity_test_with_bundle',
      'cardinality' => -1,
    ], [
      'handler' => 'default:entity_test_with_bundle',
      'handler_settings' => [
        'target_bundles' => ['test1' => 'test1', 'test2' => 'test2'],
        'auto_create' => TRUE,
        'auto_create_bundle' => 'test2',
      ],
    ], 'select2_entity_reference');

    $page = $this->getSession()->getPage();

    $this->drupalGet('/node/add/test');
    $page->fillField('title[0][value]', 'Test node');
    $this->click('.form-item-select2 .select2-selection.select2-selection--multiple');
    $page->find('css', '.select2-search__field')->setValue('New value 1');
    $page->find('css', '.select2-results__option--highlighted')->click();

    $this->click('.form-item-select2 .select2-selection.select2-selection--multiple');
    $page->find('css', '.select2-search__field')->setValue('New value 2');
    $page->find('css', '.select2-results__option--highlighted')->click();

    $page->pressButton('Save');

    $node = $this->getNodeByTitle('Test node', TRUE);
    $this->assertEquals([['target_id' => 1], ['target_id' => 2]], $node->select2->getValue());
    $entity = EntityTestWithBundle::load(1);
    $this->assertNotEmpty($entity);
    $this->assertSame('test2', $entity->bundle());
    $entity = EntityTestWithBundle::load(2);
    $this->assertNotEmpty($entity);
    $this->assertSame('test2', $entity->bundle());

    $field = FieldConfig::loadByName('node', 'test', 'select2');
    $field->setSetting('handler_settings', [
      'target_bundles' => ['test1' => 'test1', 'test2' => 'test2'],
      'auto_create' => TRUE,
      'auto_create_bundle' => 'test1',
    ]);
    $field->save();

    $this->drupalGet($node->toUrl('edit-form'));

    $this->click('.form-item-select2 .select2-selection.select2-selection--multiple');
    $page->find('css', '.select2-search__field')->setValue('New value 3');
    $page->find('css', '.select2-results__option--highlighted')->click();

    $page->pressButton('Save');

    $entity = EntityTestWithBundle::load(3);
    $this->assertNotEmpty($entity);
    $this->assertSame('test1', $entity->bundle());
  }

  /**
   * Test selecting options of different bundles.
   */
  public function testMultipleBundleSelection() {

    EntityTestBundle::create([
      'id' => 'test1',
      'label' => 'Test1 label',
      'description' => 'My test description',
    ])->save();

    EntityTestBundle::create([
      'id' => 'test2',
      'label' => 'Test2 label',
      'description' => 'My test description',
    ])->save();

    $this->createField('select2', 'node', 'test', 'entity_reference', [
      'target_type' => 'entity_test_with_bundle',
      'cardinality' => -1,
    ], [
      'handler' => 'default:entity_test_with_bundle',
      'handler_settings' => [
        'target_bundles' => ['test1' => 'test1', 'test2' => 'test2'],
        'auto_create' => FALSE,
      ],
    ], 'select2_entity_reference');

    EntityTestWithBundle::create(['name' => 'foo', 'type' => 'test1'])->save();
    EntityTestWithBundle::create(['name' => 'bar', 'type' => 'test2'])->save();
    EntityTestWithBundle::create(['name' => 'gaga', 'type' => 'test1'])->save();

    $page = $this->getSession()->getPage();

    $this->drupalGet('/node/add/test');
    $page->fillField('title[0][value]', 'Test node');

    $this->click('.form-item-select2 .select2-selection.select2-selection--multiple');
    $page->find('css', '.select2-search__field')->setValue('foo');
    $page->find('css', '.select2-results__option--highlighted')->click();

    $this->click('.form-item-select2 .select2-selection.select2-selection--multiple');
    $page->find('css', '.select2-search__field')->setValue('bar');
    $page->find('css', '.select2-results__option--highlighted')->click();

    $page->pressButton('Save');

    $node = $this->getNodeByTitle('Test node', TRUE);
    $this->assertEquals([['target_id' => 1], ['target_id' => 2]], $node->select2->getValue());
  }

  /**
   * Test that in-between ajax calls are not creating new entities.
   */
  public function testAjaxCallbacksInBetween() {

    $this->container->get('module_installer')->install(['file']);

    $this->createField('select2', 'node', 'test', 'entity_reference', [
      'target_type' => 'entity_test_mulrevpub',
    ], [
      'handler' => 'default:entity_test_mulrevpub',
      'handler_settings' => [
        'auto_create' => FALSE,
      ],
    ], 'select2_entity_reference', ['autocomplete' => TRUE]);

    $this->createField('file', 'node', 'test', 'file', [], [],
      'file_generic', []);

    $page = $this->getSession()->getPage();
    $assert_session = $this->assertSession();

    $this->drupalGet('/node/add/test');
    $page->fillField('title[0][value]', 'Test node');

    $test_file = current($this->getTestFiles('text'));
    $page->attachFileToField("files[file_0]", \Drupal::service('file_system')->realpath($test_file->uri));

    $assert_session->waitForElement('named', [
      'id_or_name',
      'file_0_remove_button',
    ]);
    $assert_session->elementNotExists('css', '.messages--error');
  }

  /**
   * Tests that the autocomplete ordering is alphabetically.
   */
  public function testAutocompleteOrdering() {
    $this->createField('select2', 'node', 'test', 'entity_reference', [
      'target_type' => 'entity_test_mulrevpub',
    ], [
      'handler' => 'default:entity_test_mulrevpub',
      'handler_settings' => [
        'auto_create' => FALSE,
      ],
    ], 'select2_entity_reference', [
      'autocomplete' => TRUE,
      'match_operator' => 'CONTAINS',
      'match_limit' => 10,
    ]);

    EntityTestMulRevPub::create(['name' => 'foo'])->save();
    EntityTestMulRevPub::create(['name' => 'bar'])->save();
    EntityTestMulRevPub::create(['name' => 'bar foo'])->save();
    EntityTestMulRevPub::create(['name' => 'gaga'])->save();

    $this->drupalGet('/node/add/test');
    $settings = Json::decode($this->getSession()->getPage()->findField('select2')->getAttribute('data-select2-config'));

    $url = Url::fromUserInput($settings['ajax']['url']);
    $url->setAbsolute(TRUE);
    $url->setRouteParameter('q', 'f');

    $response = \Drupal::httpClient()->get($url->toString());

    $results = Json::decode($response->getBody()->getContents())['results'];

    $expected = [['id' => 3, 'text' => 'bar foo'], ['id' => 1, 'text' => 'foo']];
    $this->assertSame($expected, $results);
  }

  /**
   * Tests that the autocomplete ordering is alphabetically.
   */
  public function testAutocompleteMatchLimit() {
    $this->createField('select2', 'node', 'test', 'entity_reference', [
      'target_type' => 'entity_test_mulrevpub',
    ], [
      'handler' => 'default:entity_test_mulrevpub',
      'handler_settings' => [
        'auto_create' => FALSE,
      ],
    ], 'select2_entity_reference', [
      'autocomplete' => TRUE,
      'match_operator' => 'CONTAINS',
      'match_limit' => 3,
    ]);

    EntityTestMulRevPub::create(['name' => 'foo'])->save();
    EntityTestMulRevPub::create(['name' => 'foo bar'])->save();
    EntityTestMulRevPub::create(['name' => 'bar foo'])->save();
    EntityTestMulRevPub::create(['name' => 'foooo'])->save();

    $this->drupalGet('/node/add/test');
    $settings = Json::decode($this->getSession()->getPage()->findField('select2')->getAttribute('data-select2-config'));

    $url = Url::fromUserInput($settings['ajax']['url']);
    $url->setAbsolute(TRUE);
    $url->setRouteParameter('q', 'f');

    $response = \Drupal::httpClient()->get($url->toString());

    $this->assertCount(3, Json::decode($response->getBody()->getContents())['results']);
  }

  /**
   * Tests the autocomplete drag n drop.
   */
  public function testAutocompleteDragnDrop() {
    $this->markTestSkipped(
      'Testing drag and drop is currently not possible due to a bug in chromedriver. See https://www.drupal.org/node/3084730.'
    );

    $this->createField('select2', 'node', 'test', 'entity_reference', [
      'target_type' => 'entity_test_mulrevpub',
      'cardinality' => -1,
    ], [
      'handler' => 'default:entity_test_mulrevpub',
      'handler_settings' => [
        'auto_create' => FALSE,
      ],
    ], 'select2_entity_reference', [
      'autocomplete' => TRUE,
      'match_operator' => 'CONTAINS',
    ]);

    EntityTestMulRevPub::create(['name' => 'foo'])->save();
    EntityTestMulRevPub::create(['name' => 'bar'])->save();
    EntityTestMulRevPub::create(['name' => 'gaga'])->save();

    $page = $this->getSession()->getPage();
    $assert_session = $this->assertSession();

    $this->drupalGet('/node/add/test');
    $page->fillField('title[0][value]', 'Test node');

    $this->click('.form-item-select2 .select2-selection.select2-selection--multiple');
    $page->find('css', '.select2-search__field')->setValue('fo');
    $assert_session->waitForElement('xpath', '//li[contains(@class, "select2-results__option") and text()="foo"]');
    $page->find('xpath', '//li[contains(@class, "select2-results__option") and text()="foo"]')->click();

    $this->click('.form-item-select2 .select2-selection.select2-selection--multiple');
    $page->find('css', '.select2-search__field')->setValue('ba');
    $assert_session->waitForElement('xpath', '//li[contains(@class, "select2-results__option") and text()="bar"]');
    $page->find('xpath', '//li[contains(@class, "select2-results__option") and text()="bar"]')->click();

    $this->click('.form-item-select2 .select2-selection.select2-selection--multiple');
    $page->find('css', '.select2-search__field')->setValue('ga');
    $assert_session->waitForElement('xpath', '//li[contains(@class, "select2-results__option") and text()="gaga"]');
    $page->find('xpath', '//li[contains(@class, "select2-results__option") and text()="gaga"]')->click();

    $this->dragDropElement($page->find('xpath', '//li[contains(@class, "select2-selection__choice") and text()="gaga"]'), -100, 0);
    $this->dragDropElement($page->find('xpath', '//li[contains(@class, "select2-selection__choice") and text()="foo"]'), 50, 0);

    $page->pressButton('Save');

    $node = $this->getNodeByTitle('Test node', TRUE);
    $this->assertEquals([
      ['target_id' => 3],
      ['target_id' => 2],
      ['target_id' => 1],
    ], $node->select2->getValue());
  }

}
