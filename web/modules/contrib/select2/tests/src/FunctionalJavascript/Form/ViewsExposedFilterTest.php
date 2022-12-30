<?php

namespace Drupal\Tests\select2\FunctionalJavascript\Form;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;

/**
 * Tests the select2 element in exposed views filter.
 *
 * @group select2
 */
class ViewsExposedFilterTest extends WebDriverTestBase {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['select2_form_test', 'node', 'views'];

  /**
   * Tests select2 optgroups.
   */
  public function testContentListWithSelect2Filter() {
    $page = $this->getSession()->getPage();

    $admin = $this->createUser([
      'access content overview',
      'view own unpublished content',
    ]);

    $node_type = $this->createContentType();

    $this->drupalCreateNode([
      'title' => $this->t('Node1'),
      'type' => $node_type->id(),
      'uid' => $admin->id(),
    ]);

    $this->drupalCreateNode([
      'title' => $this->t('Node2'),
      'type' => $node_type->id(),
      'status' => FALSE,
      'uid' => $admin->id(),
    ]);

    $this->drupalLogin($admin);
    $this->drupalGet('/admin/content');

    $this->assertSession()->pageTextContains('Node1');
    $this->assertSession()->pageTextContains('Node2');

    $this->click('.form-item-status .select2-selection.select2-selection--single');
    $this->assertNotEmpty($this->assertSession()->waitForElementVisible('css', '.select2-results__options'));
    $page->find('css', '.select2-search__field')->setValue('Published');
    $page->find('css', '.select2-results__option--highlighted')->click();

    $page->pressButton('Filter');

    $this->assertSession()->pageTextContains('Node1');
    $this->assertSession()->pageTextNotContains('Node2');

    $this->click('.form-item-status .select2-selection.select2-selection--single');
    $this->assertNotEmpty($this->assertSession()->waitForElementVisible('css', '.select2-results__options'));
    $page->find('css', '.select2-search__field')->setValue('Unpublished');
    $page->find('css', '.select2-results__option--highlighted')->click();

    $page->pressButton('Filter');

    $this->assertSession()->pageTextNotContains('Node1');
    $this->assertSession()->pageTextContains('Node2');

    $this->click('.form-item-status .select2-selection.select2-selection--single');
    $this->assertNotEmpty($this->assertSession()->waitForElementVisible('css', '.select2-results__options'));
    $page->find('css', '.select2-search__field')->setValue('Any');
    $page->find('css', '.select2-results__option--highlighted')->click();

    $page->pressButton('Filter');

    $this->assertSession()->pageTextContains('Node1');
    $this->assertSession()->pageTextContains('Node2');
  }

}
