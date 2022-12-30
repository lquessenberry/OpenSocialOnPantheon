<?php

namespace Drupal\Tests\search_api\Kernel\Server;

use Drupal\Core\Config\Entity\ConfigEntityStorage;
use Drupal\search_api\ServerInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\search_api_test\PluginTestTrait;

/**
 * Tests whether the storage of search servers works correctly.
 *
 * @group search_api
 */
class ServerStorageTest extends KernelTestBase {

  use PluginTestTrait;

  /**
   * Modules to enable for this test.
   *
   * @var string[]
   */
  protected static $modules = [
    'search_api',
    'search_api_test',
    'user',
    'system',
  ];

  /**
   * The search server storage.
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
    $this->storage = $this->container->get('entity_type.manager')->getStorage('search_api_server');
  }

  /**
   * Tests all CRUD operations as a queue of operations.
   */
  public function testServerCrud() {
    $this->assertInstanceOf(ConfigEntityStorage::class, $this->storage, 'The Search API Server storage controller is loaded.');

    $server = $this->serverCreate();
    $this->serverLoad($server);
    $this->serverUpdate($server);
    $this->serverDelete($server);
  }

  /**
   * Tests whether creating a server works correctly.
   *
   * @return \Drupal\search_api\ServerInterface
   *   The newly created search server.
   */
  public function serverCreate() {
    $server_data = [
      'id' => 'test_server',
      'name' => 'Test server',
      'backend' => 'search_api_test',
    ];
    $server = $this->storage->create($server_data);

    $this->assertInstanceOf(ServerInterface::class, $server, 'The newly created entity is a Search API Server.');
    $server->save();

    $key = 'search_api_test.methods_called.' . $server->id();
    $methods_called = \Drupal::state()->get($key, []);
    $this->assertNotContains('preUpdate', $methods_called, 'Backend::preUpdate() not called for initial save.');
    $this->assertNotContains('postUpdate', $methods_called, 'Backend::postUpdate() not called for initial save.');

    return $server;
  }

  /**
   * Tests whether loading a server works correctly.
   *
   * @param \Drupal\search_api\ServerInterface $server
   *   The server used for this test.
   */
  public function serverLoad(ServerInterface $server) {
    $loaded_server = $this->storage->load($server->id());
    $this->assertSame($server->label(), $loaded_server->label());

    $this->storage->resetCache();
    $loaded_server = $this->storage->load($server->id());
    $this->assertSame($server->label(), $loaded_server->label());
  }

  /**
   * Tests whether updating a server works correctly.
   *
   * @param \Drupal\search_api\ServerInterface $server
   *   The server used for this test.
   */
  public function serverUpdate(ServerInterface $server) {
    $server->set('name', $server->label() . ' - edited');
    $server->save();

    $methods_called = $this->getCalledMethods('backend');
    $this->assertContains('preUpdate', $methods_called, 'Backend::preUpdate() called for update.');
    $this->assertContains('postUpdate', $methods_called, 'Backend::postUpdate() called for update.');

    $this->serverLoad($server);
  }

  /**
   * Tests whether deleting a server works correctly.
   *
   * @param \Drupal\search_api\ServerInterface $server
   *   The server used for this test.
   */
  public function serverDelete(ServerInterface $server) {
    $this->storage->delete([$server]);
    $loaded_server = $this->storage->load($server->id());
    $this->assertNull($loaded_server);
  }

}
