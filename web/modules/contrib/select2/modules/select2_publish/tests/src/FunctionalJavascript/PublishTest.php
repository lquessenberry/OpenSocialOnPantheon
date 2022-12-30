<?php

namespace Drupal\Tests\select2_publish\FunctionalJavascript;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Url;
use Drupal\entity_test\Entity\EntityTestMulRevPub;
use Drupal\Tests\select2\FunctionalJavascript\Select2JavascriptTestBase;

/**
 * Tests select2_publish integration.
 *
 * @group select2
 */
class PublishTest extends Select2JavascriptTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['entity_test', 'select2_publish'];

  /**
   * Test autocomplete in a single value field.
   */
  public function testMultipleSelection() {
    $this->createField('select2', 'node', 'test', 'entity_reference', [
      'target_type' => 'entity_test_mulrevpub',
      'cardinality' => -1,
    ], [
      'handler' => 'default:entity_test_mulrevpub',
      'handler_settings' => [
        'target_bundles' => ['entity_test_mulrevpub' => 'entity_test_mulrevpub'],
        'auto_create' => FALSE,
      ],
    ], 'select2_entity_reference');

    EntityTestMulRevPub::create(['name' => 'foo'])->save();
    EntityTestMulRevPub::create(['name' => 'bar', 'status' => FALSE])->save();
    EntityTestMulRevPub::create(['name' => 'gaga'])->save();

    $page = $this->getSession()->getPage();
    $assert_session = $this->assertSession();

    $this->drupalGet('/node/add/test');
    $page->fillField('title[0][value]', 'Test node');
    $this->click('.form-item-select2 .select2-selection.select2-selection--multiple');

    $page->find('css', '.select2-search__field')->setValue('fo');
    $this->assertNotEmpty($assert_session->waitForElement('xpath', '//li[@class="select2-results__option published select2-results__option--highlighted" and text()="foo"]'));
    $page->find('xpath', '//li[@class="select2-results__option published select2-results__option--highlighted" and text()="foo"]')->click();
    $this->assertNotEmpty($assert_session->waitForElement('xpath', '//li[@class="select2-selection__choice published" and text()="foo"]'));

    $page->find('css', '.select2-search__field')->setValue('ba');
    $this->assertNotEmpty($assert_session->waitForElement('xpath', '//li[@class="select2-results__option unpublished select2-results__option--highlighted" and text()="bar"]'));
    $page->find('xpath', '//li[@class="select2-results__option unpublished select2-results__option--highlighted" and text()="bar"]')->click();
    $this->assertNotEmpty($assert_session->waitForElement('xpath', '//li[@class="select2-selection__choice unpublished" and text()="bar"]'));
  }

  /**
   * Tests that the autocomplete.
   */
  public function testAutocomplete() {
    $this->createField('select2', 'node', 'test', 'entity_reference', [
      'target_type' => 'entity_test_mulrevpub',
    ], [
      'handler' => 'default:entity_test_mulrevpub',
      'handler_settings' => [
        'target_bundles' => ['entity_test_mulrevpub' => 'entity_test_mulrevpub'],
        'auto_create' => FALSE,
      ],
    ], 'select2_entity_reference', [
      'autocomplete' => TRUE,
      'match_operator' => 'CONTAINS',
    ]);

    EntityTestMulRevPub::create(['name' => 'foo', 'status' => FALSE])->save();
    EntityTestMulRevPub::create(['name' => 'bar'])->save();
    EntityTestMulRevPub::create(['name' => 'bar foo'])->save();
    EntityTestMulRevPub::create(['name' => 'gaga', 'status' => FALSE])->save();

    $this->drupalGet('/node/add/test');
    $settings = Json::decode($this->getSession()->getPage()->findField('select2')->getAttribute('data-select2-config'));

    $url = Url::fromUserInput($settings['ajax']['url']);
    $url->setAbsolute(TRUE);
    $url->setRouteParameter('q', 'f');

    $response = \Drupal::httpClient()->get($url->toString());

    $results = Json::decode($response->getBody()->getContents())['results'];

    $expected = [
      ['id' => 3, 'text' => 'bar foo', 'published' => TRUE],
      ['id' => 1, 'text' => 'foo', 'published' => FALSE],
    ];
    $this->assertSame($expected, $results);

    $page = $this->getSession()->getPage();
    $assert_session = $this->assertSession();

    $page->fillField('title[0][value]', 'Test node');
    $this->click('.form-item-select2 .select2-selection.select2-selection--single');

    $page->find('css', '.select2-search__field')->setValue('gag');
    $assert_session->waitForElement('xpath', '//li[@class="select2-results__option unpublished select2-results__option--highlighted" and text()="gaga"]');
    $page->find('xpath', '//li[@class="select2-results__option unpublished select2-results__option--highlighted" and text()="gaga"]')->click();
    $page->pressButton('Save');

    $node = $this->getNodeByTitle('Test node', TRUE);
    $this->assertEquals([['target_id' => 4]], $node->select2->getValue());
  }

}
