<?php

namespace Drupal\Tests\typed_data\Functional\TypedDataFormWidget;

use Drupal\Core\TypedData\TypedDataTrait;
use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\typed_data\Traits\BrowserTestHelpersTrait;
use Drupal\typed_data\Widget\FormWidgetManagerTrait;

/**
 * Base class that all TypedDataFormWidget tests should extend from.
 *
 * @group typed_data
 */
abstract class FormWidgetBrowserTestBase extends BrowserTestBase {

  use BrowserTestHelpersTrait;
  use FormWidgetManagerTrait;
  use TypedDataTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'typed_data',
    'typed_data_widget_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * @covers ::isApplicable
   */
  public function testIsApplicable() {
    // Force any tests that extend this class to implement their own version
    // of this function.
    $this->fail('There is no implementation of the mandatory test function: ' . $this->getName());
  }

  /**
   * @covers ::form
   * @covers ::extractFormValues
   */
  public function testFormEditing() {
    $this->fail('There is no implementation of the mandatory test function: ' . $this->getName());
  }

  /**
   * @covers ::form
   * @covers ::flagViolations
   */
  public function testValidation() {
    $this->fail('There is no implementation of the mandatory test function: ' . $this->getName());
  }

}
