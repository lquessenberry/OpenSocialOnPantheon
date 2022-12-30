<?php

namespace Drupal\Tests\select2\Kernel;

use Drupal\entity_test\Entity\EntityTest;
use Drupal\entity_test\Entity\EntityTestMulRevPub;

/**
 * Test the options of the select2 element.
 *
 * @group select2
 */
class Select2ValidOptionsTest extends Select2KernelTestBase {

  /**
   * Tests that available options are set according to values.
   */
  public function testAvailableOptions() {

    $name = 'test_select2';

    $storage_settings = [
      'target_type' => 'entity_test_mulrevpub',
      'cardinality' => -1,
    ];
    $field_settings = [
      'handler' => 'default:entity_test_mulrevpub',
      'handler_settings' => [
        'target_bundles' => ['entity_test_mulrevpub' => 'entity_test_mulrevpub'],
        'auto_create' => TRUE,
      ],
    ];
    $this->createField($name, 'entity_test', 'entity_test', 'entity_reference', $storage_settings, $field_settings, 'select2_entity_reference', ['autocomplete' => TRUE]);

    $entity = EntityTest::create();
    $ref1 = EntityTestMulRevPub::create(['name' => 'Drupal Temp']);
    $ref2 = EntityTestMulRevPub::create(['name' => 'Test']);
    $ref1->save();
    $ref2->save();

    // Create a new revision to trigger problem.
    $ref1->setName('Drupal')->setNewRevision();
    $ref1->save();

    $entity->$name->setValue([
      ['target_id' => $ref1->id()],
      ['target_id' => $ref2->id()],
    ]);
    $entity->save();

    $form = \Drupal::service('entity.form_builder')->getForm($entity);
    $this->assertEquals([
      $ref1->id() => $ref1->getName(),
      $ref2->id() => $ref2->getName(),
    ], $form[$name]['widget']['#options'], 'Option values differ from expected values.');
  }

}
