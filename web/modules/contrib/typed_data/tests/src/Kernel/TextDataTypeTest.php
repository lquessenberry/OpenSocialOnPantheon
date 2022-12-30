<?php

namespace Drupal\Tests\typed_data\Kernel;

use Drupal\Core\TypedData\DataDefinition;
use Drupal\Core\TypedData\Type\StringInterface;
use Drupal\Core\TypedData\TypedDataInterface;
use Drupal\KernelTests\KernelTestBase;

/**
 * Tests the functionality of the Text datatype.
 *
 * @group typed_data
 */
class TextDataTypeTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'typed_data'];

  /**
   * Tests that the Text datatype is instantiated correctly.
   *
   * Based on core/tests/Drupal/KernelTests/Core/TypedData/TypedDataTest.php.
   */
  public function testTextDatatype() {
    // Create the Text datatype object. Use a multi-line value.
    $value = $this->randomString() . "\r\n" . $this->randomString();
    $definition = DataDefinition::create('text');
    $typed_data = $this->container->get('typed_data_manager')->create($definition, $value, 'some_text_name');

    // Check that the new object is an instance of TypedDataInterface and of
    // StringInterface (not TextInterface).
    $this->assertInstanceOf(TypedDataInterface::class, $typed_data, 'Typed Data object is an instance of the typed data interface.');
    $this->assertInstanceOf(StringInterface::class, $typed_data, 'Typed Data object is an instance of StringInterface).');

    // Check basic get and set functionality.
    $this->assertSame($value, $typed_data->getValue(), 'Text value was fetched.');
    $this->assertEquals(0, $typed_data->validate()->count());
    $new_value = $this->randomString() . "\r\n" . $this->randomString();
    $typed_data->setValue($new_value);
    $this->assertSame($new_value, $typed_data->getValue(), 'Text value was changed.');
    $this->assertEquals(0, $typed_data->validate()->count());
  }

}
