<?php

namespace Drupal\Tests\search_api\Kernel\Index;

use Drupal\Core\Config\Entity\ConfigEntityStorage;
use Drupal\search_api\IndexInterface;
use Drupal\KernelTests\KernelTestBase;

/**
 * Tests whether the storage of search indexes works correctly.
 *
 * @group search_api
 */
class IndexStorageTest extends KernelTestBase {

  /**
   * Modules to enable for this test.
   *
   * @var string[]
   */
  protected static $modules = ['search_api', 'user', 'system'];

  /**
   * The search index storage.
   *
   * @var \Drupal\Core\Config\Entity\ConfigEntityStorageInterface
   */
  protected $storage;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('search_api_task');
    $this->installConfig('search_api');

    $this->storage = $this->container
      ->get('entity_type.manager')
      ->getStorage('search_api_index');
  }

  /**
   * Tests all CRUD operations as a queue of operations.
   */
  public function testIndexCrud() {
    $this->assertInstanceOf(ConfigEntityStorage::class, $this->storage, 'The Search API Index storage controller is loaded.');

    $index = $this->indexCreate();
    $this->indexLoad($index);
    $this->indexDelete($index);
  }

  /**
   * Tests whether creating an index works correctly.
   *
   * @return \Drupal\search_api\IndexInterface
   *   The newly created search index.
   */
  protected function indexCreate() {
    $index_data = [
      'id' => 'test',
      'name' => 'Index test name',
    ];

    $index = $this->storage->create($index_data);
    $this->assertInstanceOf(IndexInterface::class, $index, 'The newly created entity is a search index.');
    $index->save();

    return $index;
  }

  /**
   * Tests whether loading an index works correctly.
   *
   * @param \Drupal\search_api\IndexInterface $index
   *   The index used for the test.
   */
  protected function indexLoad(IndexInterface $index) {
    $loaded_index = $this->storage->load($index->id());
    $this->assertSame($index->label(), $loaded_index->label());
  }

  /**
   * Tests whether deleting an index works correctly.
   *
   * @param \Drupal\search_api\IndexInterface $index
   *   The index used for the test.
   */
  protected function indexDelete(IndexInterface $index) {
    $this->storage->delete([$index]);
    $loaded_index = $this->storage->load($index->id());
    $this->assertNull($loaded_index);
  }

}
