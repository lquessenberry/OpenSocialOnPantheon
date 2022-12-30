<?php

namespace Drupal\Tests\typed_data\Functional\TypedDataFormWidget;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\TypedData\DataDefinition;
use Drupal\Core\TypedData\ListDataDefinition;
use Drupal\Core\TypedData\MapDataDefinition;

/**
 * Tests operation of the 'datetime' TypedDataForm widget plugin.
 *
 * @group typed_data
 *
 * @coversDefaultClass \Drupal\typed_data\Plugin\TypedDataFormWidget\DatetimeWidget
 */
class DatetimeWidgetTest extends FormWidgetBrowserTestBase {

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
    $this->widget = $this->getFormWidgetManager()->createInstance('datetime');
    $this->drupalLogin($this->createUser([], NULL, TRUE));
  }

  /**
   * @covers ::isApplicable
   */
  public function testIsApplicable() {
    $this->assertFalse($this->widget->isApplicable(DataDefinition::create('any')));
    $this->assertFalse($this->widget->isApplicable(DataDefinition::create('binary')));
    $this->assertFalse($this->widget->isApplicable(DataDefinition::create('boolean')));
    $this->assertTrue($this->widget->isApplicable(DataDefinition::create('datetime_iso8601')));
    $this->assertFalse($this->widget->isApplicable(DataDefinition::create('duration_iso8601')));
    $this->assertFalse($this->widget->isApplicable(DataDefinition::create('email')));
    $this->assertFalse($this->widget->isApplicable(DataDefinition::create('float')));
    $this->assertFalse($this->widget->isApplicable(DataDefinition::create('integer')));
    $this->assertFalse($this->widget->isApplicable(DataDefinition::create('string')));
    $this->assertFalse($this->widget->isApplicable(DataDefinition::create('timespan')));
    $this->assertTrue($this->widget->isApplicable(DataDefinition::create('timestamp')));
    $this->assertFalse($this->widget->isApplicable(DataDefinition::create('uri')));
    $this->assertFalse($this->widget->isApplicable(ListDataDefinition::create('string')));
    $this->assertFalse($this->widget->isApplicable(MapDataDefinition::create()));
  }

  /**
   * @covers ::form
   * @covers ::extractFormValues
   */
  public function testFormEditing() {
    $context_definition = ContextDefinition::create('datetime_iso8601')
      ->setLabel('Test Example Date and Time')
      ->setDescription('Enter the date and time.')
      ->setDefaultValue('2017-04-18T06:20:52');
    $this->container->get('state')->set('typed_data_widgets.definition', $context_definition);

    $path = 'admin/config/user-interface/typed-data-widgets/' . $this->widget->getPluginId();
    $this->drupalGet($path);

    /** @var \Drupal\Tests\WebAssert $assert */
    $assert = $this->assertSession();

    // Unlike other form widgets where the label is directly related to the
    // single input field and we use "label[for=edit-data-value]", the datetime
    // widget has two inputs (date and time) and the label is in a separate
    // preceding h4 tag. When using the defaultTheme 'stark' this does not even
    // have a class of 'label' so all we can do is check the text.
    $assert->elementExists('xpath',
      '//div[@id="edit-data-value"]/preceding-sibling::h4[contains(text(), "' . $context_definition->getLabel() . '")]');
    $assert->elementTextContains('css', 'div[id=edit-data-value--description]', $context_definition->getDescription());

    // Check that getDefaultValue() returns the expected value.
    $default = new DrupalDateTime($context_definition->getDefaultValue());
    $this->assertEquals('2017-04-18', $default->format('Y-m-d'));
    $this->assertEquals('06:20:52', $default->format('H:i:s'));

    // Check that the fields have the expected default values.
    $assert->fieldValueEquals('data[value][date]', '2017-04-18');
    $assert->fieldValueEquals('data[value][time]', '06:20:52');

    // Set a value for the date and time and save the form.
    $this->fillField('data[value][date]', '2020-01-28');
    $this->fillField('data[value][time]', '14:00:00');
    $this->pressButton('Submit');

    // Check that the values were saved.
    $assert->pageTextContains('Value saved');
    $assert->fieldValueEquals('data[value][date]', '2020-01-28');
    $assert->fieldValueEquals('data[value][time]', '14:00:00');

    // Check that Reset restores the value back to the default.
    $this->pressButton('Reset');
    $assert->pageTextContains('Value reset to default');
    $assert->fieldValueEquals('data[value][date]', '2017-04-18');
    $assert->fieldValueEquals('data[value][time]', '06:20:52');
  }

  /**
   * @covers ::form
   * @covers ::flagViolations
   */
  public function testValidation() {
    $context_definition = ContextDefinition::create('datetime_iso8601')
      ->setLabel('Test Date and Time')
      ->setDefaultValue('2017-04-18T06:20:52');
    $this->container->get('state')->set('typed_data_widgets.definition', $context_definition);

    /** @var \Drupal\Tests\WebAssert $assert */
    $assert = $this->assertSession();

    $path = 'admin/config/user-interface/typed-data-widgets/' . $this->widget->getPluginId();
    $this->drupalGet($path);

    // Try to save with no date.
    $this->fillField('data[value][date]', '');
    $this->pressButton('Submit');
    $assert->fieldExists('data[value][date]')->hasClass('error');

    // Make sure the changes have not been saved.
    $this->drupalGet($path);
    $assert->fieldValueEquals('data[value][date]', '2017-04-18');
    $assert->fieldValueEquals('data[value][time]', '06:20:52');

    // Now try to save with no time.
    $this->fillField('data[value][time]', '');
    $this->pressButton('Submit');
    $assert->fieldExists('data[value][time]')->hasClass('error');
  }

  /**
   * @covers Drupal\typed_data\Widget\FormWidgetBase::createDefaultDateTime
   */
  public function testNoDefault() {
    $context_definition = ContextDefinition::create('datetime_iso8601')
      ->setLabel('Test Example Date and Time');
    $this->container->get('state')->set('typed_data_widgets.definition', $context_definition);

    $path = 'admin/config/user-interface/typed-data-widgets/' . $this->widget->getPluginId();
    $this->drupalGet($path);

    /** @var \Drupal\Tests\WebAssert $assert */
    $assert = $this->assertSession();

    // Check that empty date and 12:00:00 for the time are shown when no default
    // is specified in the configuration.
    $assert->fieldValueEquals('data[value][date]', '0000-01-01');
    $assert->fieldValueEquals('data[value][time]', '12:00:00');
  }

}
