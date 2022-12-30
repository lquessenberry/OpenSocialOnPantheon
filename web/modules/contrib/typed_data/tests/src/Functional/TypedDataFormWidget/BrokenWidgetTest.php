<?php

namespace Drupal\Tests\typed_data\Functional\TypedDataFormWidget;

use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\TypedData\DataDefinition;
use Drupal\Core\TypedData\ListDataDefinition;
use Drupal\Core\TypedData\MapDataDefinition;

/**
 * Tests operation of the 'broken' TypedDataForm widget plugin.
 *
 * @group typed_data
 *
 * @coversDefaultClass \Drupal\typed_data\Plugin\TypedDataFormWidget\BrokenWidget
 */
class BrokenWidgetTest extends FormWidgetBrowserTestBase {

  /**
   * The tested form widget.
   *
   * @var \Drupal\typed_data\Widget\FormWidgetInterface
   */
  protected $widget;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->widget = $this->getFormWidgetManager()->createInstance('broken');
  }

  /**
   * @covers ::isApplicable
   */
  public function testIsApplicable() {
    $this->assertTrue($this->widget->isApplicable(DataDefinition::create('any')));
    $this->assertTrue($this->widget->isApplicable(DataDefinition::create('binary')));
    $this->assertTrue($this->widget->isApplicable(DataDefinition::create('boolean')));
    $this->assertTrue($this->widget->isApplicable(DataDefinition::create('datetime_iso8601')));
    $this->assertTrue($this->widget->isApplicable(DataDefinition::create('duration_iso8601')));
    $this->assertTrue($this->widget->isApplicable(DataDefinition::create('email')));
    $this->assertTrue($this->widget->isApplicable(DataDefinition::create('float')));
    $this->assertTrue($this->widget->isApplicable(DataDefinition::create('integer')));
    $this->assertTrue($this->widget->isApplicable(DataDefinition::create('string')));
    $this->assertTrue($this->widget->isApplicable(DataDefinition::create('timespan')));
    $this->assertTrue($this->widget->isApplicable(DataDefinition::create('timestamp')));
    $this->assertTrue($this->widget->isApplicable(DataDefinition::create('uri')));
    $this->assertTrue($this->widget->isApplicable(ListDataDefinition::create('string')));
    $this->assertTrue($this->widget->isApplicable(MapDataDefinition::create()));
  }

  /**
   * @covers ::form
   * @covers ::extractFormValues
   */
  public function testFormEditing() {
    $data_type = 'string';
    $context_definition = ContextDefinition::create($data_type)
      ->setLabel('Broken example');
    $this->container->get('state')->set('typed_data_widgets.definition', $context_definition);

    $this->drupalLogin($this->createUser([], NULL, TRUE));
    $path = 'admin/config/user-interface/typed-data-widgets/' . $this->widget->getPluginId();
    $this->drupalGet($path);

    /** @var \Drupal\Tests\WebAssert $assert */
    $assert = $this->assertSession();
    $assert->elementTextContains('css', 'label[for=edit-data-value]', $context_definition->getLabel());
    $assert->elementTextContains('css', 'div[id=edit-data-value]', 'No widget exists for this data type.');

    $this->pressButton('Submit');

    $assert->pageTextContains(sprintf('The field %s consists of the data type %s which cannot be input or a widget for this data type is not implemented yet.', $context_definition->getLabel(), $data_type));
  }

  /**
   * @covers ::form
   * @covers ::flagViolations
   */
  public function testValidation() {
    // No validation as there is no input widget.
  }

}
