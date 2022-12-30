<?php

namespace Drupal\Tests\typed_data\Functional\TypedDataFormWidget;

use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\TypedData\DataDefinition;
use Drupal\Core\TypedData\ListDataDefinition;
use Drupal\Core\TypedData\MapDataDefinition;

/**
 * Tests operation of the 'textarea' TypedDataForm widget plugin.
 *
 * @group typed_data
 *
 * @coversDefaultClass \Drupal\typed_data\Plugin\TypedDataFormWidget\TextareaWidget
 */
class TextareaWidgetTest extends FormWidgetBrowserTestBase {

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
    $this->widget = $this->getFormWidgetManager()->createInstance('textarea');
  }

  /**
   * @covers ::isApplicable
   */
  public function testIsApplicable() {
    $this->assertFalse($this->widget->isApplicable(DataDefinition::create('any')));
    $this->assertFalse($this->widget->isApplicable(DataDefinition::create('binary')));
    $this->assertFalse($this->widget->isApplicable(DataDefinition::create('boolean')));
    $this->assertFalse($this->widget->isApplicable(DataDefinition::create('datetime_iso8601')));
    $this->assertFalse($this->widget->isApplicable(DataDefinition::create('duration_iso8601')));
    $this->assertFalse($this->widget->isApplicable(DataDefinition::create('email')));
    $this->assertFalse($this->widget->isApplicable(DataDefinition::create('float')));
    $this->assertFalse($this->widget->isApplicable(DataDefinition::create('integer')));
    $this->assertTrue($this->widget->isApplicable(DataDefinition::create('string')));
    $this->assertFalse($this->widget->isApplicable(DataDefinition::create('timespan')));
    $this->assertFalse($this->widget->isApplicable(DataDefinition::create('timestamp')));
    $this->assertFalse($this->widget->isApplicable(DataDefinition::create('uri')));
    $this->assertFalse($this->widget->isApplicable(ListDataDefinition::create('string')));
    $this->assertFalse($this->widget->isApplicable(MapDataDefinition::create()));
  }

  /**
   * @covers ::form
   * @covers ::extractFormValues
   */
  public function testFormEditing() {
    $context_definition = ContextDefinition::create('string')
      ->setLabel('Example textarea')
      ->setDescription('Some example textarea')
      ->setDefaultValue('A string longer than eight characters');
    $this->container->get('state')->set('typed_data_widgets.definition', $context_definition);

    $this->drupalLogin($this->createUser([], NULL, TRUE));
    $path = 'admin/config/user-interface/typed-data-widgets/' . $this->widget->getPluginId();
    $this->drupalGet($path);

    /** @var \Drupal\Tests\WebAssert $assert */
    $assert = $this->assertSession();
    $assert->elementTextContains('css', 'label[for=edit-data-value]', $context_definition->getLabel());
    $assert->elementTextContains('css', 'div[id=edit-data-value--description]', $context_definition->getDescription());
    $assert->fieldValueEquals('data[value]', $context_definition->getDefaultValue());

    $this->fillField('data[value]', 'jump');
    $this->pressButton('Submit');

    $this->drupalGet($path);
    $assert->fieldValueEquals('data[value]', 'jump');
  }

  /**
   * @covers ::form
   * @covers ::flagViolations
   */
  public function testValidation() {
    $context_definition = ContextDefinition::create('text')
      ->setLabel('Test text area')
      ->setDescription('Enter text, minimum 40 characters.');
    // Omitting the 'allowEmptyString' argument in Symfony 4+ (which is used in
    // Drupal 9.0+) gives a deprecation warning, but this option does not exist
    // in Symfony 3.4 (which is used in Drupal 8.8 and 8.9).
    // @see https://www.drupal.org/project/typed_data/issues/3161000
    if (version_compare(\Drupal::VERSION, '9.0', '>=')) {
      $context_definition->addConstraint('Length', ['min' => 40, 'allowEmptyString' => FALSE]);
    }
    else {
      $context_definition->addConstraint('Length', ['min' => 40]);
    }
    $this->container->get('state')->set('typed_data_widgets.definition', $context_definition);

    $this->drupalLogin($this->createUser([], NULL, TRUE));
    $path = 'admin/config/user-interface/typed-data-widgets/' . $this->widget->getPluginId();
    $this->drupalGet($path);

    // Try to save with text that is too short.
    $this->fillField('data[value]', $this->randomString(20));
    $this->pressButton('Submit');

    /** @var \Drupal\Tests\WebAssert $assert */
    $assert = $this->assertSession();
    $assert->fieldExists('data[value]')->hasClass('error');

    // Make sure the changes have not been saved.
    $this->drupalGet($path);
    $assert->fieldValueEquals('data[value]', $context_definition->getDefaultValue());
  }

}
