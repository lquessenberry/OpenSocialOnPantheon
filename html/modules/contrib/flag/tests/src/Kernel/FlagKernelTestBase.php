<?php
namespace Drupal\Tests\flag\Kernel;

use Drupal\Core\Session\AccountInterface;
use Drupal\flag\FlagInterface;
use Drupal\flag\Tests\FlagCreateTrait;
use Drupal\KernelTests\KernelTestBase;
use Drupal\simpletest\UserCreationTrait;

/**
 * Basic setup for kernel tests based around flaggings articles.
 */
abstract class FlagKernelTestBase extends KernelTestBase {

  use FlagCreateTrait;
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
  public static $modules = [
    'field',
    'filter',
    'flag',
    'node',
    'text',
    'user',
    'system',
  ];

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
    $this->installConfig(['filter', 'flag', 'node']);

    $this->flagService = \Drupal::service('flag');
  }

  /**
   * Get all flaggings for the given flag.
   *
   * @param \Drupal\flag\FlagInterface $flag
   *   The flag entity.
   *
   * @return \Drupal\flag\FlaggingInterface[]
   *   An array of flaggings.
   */
  protected function getFlagFlaggings(FlagInterface $flag) {
    $query = \Drupal::entityQuery('flagging');
    $query->condition('flag_id', $flag->id());
    $ids = $query->execute();

    return \Drupal::entityTypeManager()->getStorage('flagging')->loadMultiple($ids);
  }
}
