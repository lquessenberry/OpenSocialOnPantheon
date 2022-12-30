<?php

namespace Drupal\Tests\search_api\Unit;

use Drupal\search_api\DataType\DataTypeInterface;
use Drupal\search_api\Entity\Index;
use Drupal\search_api\Item\Field;
use Drupal\Tests\UnitTestCase;

/**
 * Tests functionality of the field class.
 *
 * @coversDefaultClass \Drupal\search_api\Item\Field
 *
 * @group search_api
 */
class ItemFieldTest extends UnitTestCase {

  /**
   * The field object being tested.
   *
   * @var \Drupal\search_api\Item\Field
   */
  protected $field;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $data_type = $this->createMock(DataTypeInterface::class);
    $data_type->expects($this->any())
      ->method('getValue')
      ->willReturnCallback(function ($v) {
        return "*$v";
      });

    /** @var \Drupal\search_api\DataType\DataTypePluginManager|\PHPUnit\Framework\MockObject\MockObject $data_type_manager */
    $data_type_manager = $this->getMockBuilder('Drupal\search_api\DataType\DataTypePluginManager')
      ->disableOriginalConstructor()
      ->getMock();
    $data_type_manager->expects($this->any())
      ->method('hasDefinition')
      ->willReturn(TRUE);
    $data_type_manager->expects($this->any())
      ->method('createInstance')
      ->willReturn($data_type);

    $index = new Index([], 'search_api_index');

    $this->field = new Field($index, 'field');
    $this->field->setDataTypeManager($data_type_manager);
  }

  /**
   * Tests setting the Values.
   *
   * @covers ::setValues
   */
  public function testSetValues() {
    $values = ['*foo', '*bar'];
    $this->field->setValues($values);
    $this->assertEquals($values, $this->field->getValues());
  }

  /**
   * Tests adding a value.
   *
   * Ensures that a string passed to addValue() is processed by the data type
   * plugin.
   *
   * @covers ::addValue
   */
  public function testAddValue() {
    $this->field->setValues(['*foo']);
    $this->field->addValue('bar');
    $this->assertEquals(['*foo', '*bar'], $this->field->getValues());
  }

}
