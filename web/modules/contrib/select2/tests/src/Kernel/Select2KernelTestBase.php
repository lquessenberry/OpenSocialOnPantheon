<?php

namespace Drupal\Tests\select2\Kernel;

use Drupal\Tests\field\Kernel\FieldKernelTestBase;
use Drupal\Tests\select2\Traits\Select2TestTrait;

/**
 * Base class for Select2 module integration tests.
 */
abstract class Select2KernelTestBase extends FieldKernelTestBase {

  use Select2TestTrait;

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = ['select2'];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    $this->container->get('router.builder')->rebuild();

    $this->installEntitySchema('entity_test_mulrevpub');
  }

}
