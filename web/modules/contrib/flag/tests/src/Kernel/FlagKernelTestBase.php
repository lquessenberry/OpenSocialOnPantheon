<?php
namespace Drupal\Tests\flag\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\simpletest\UserCreationTrait;

/**
 * Basic setup for kernel tests based around flaggings articles.
 */
abstract class FlagKernelTestBase extends KernelTestBase {

  use UserCreationTrait;

  /**
   * The flag service.
   *
   * @var \Drupal\flag\FlagServiceInterface
   */
  protected $flagService;

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = ['flag', 'node', 'user', 'system'];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('flagging');
    $this->installSchema('system', ['sequences']);
    $this->installSchema('flag', ['flag_counts']);
    $this->installSchema('node', ['node_access']);

    $this->flagService = \Drupal::service('flag');
  }

}
