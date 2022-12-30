<?php

namespace Drupal\Tests\group\Unit;

use Drupal\Component\Plugin\Discovery\DiscoveryInterface;
use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\Entity\ConfigEntityStorageInterface;
use Drupal\Core\Entity\ContentEntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\group\Plugin\GroupContentEnablerManager;
use Drupal\group\Plugin\GroupContentHandlerBase;
use Drupal\Tests\UnitTestCase;
use Prophecy\Argument;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Tests the GroupContentEnabler plugin manager.
 *
 * @coversDefaultClass \Drupal\group\Plugin\GroupContentEnablerManager
 * @group group
 */
class GroupContentEnablerManagerTest extends UnitTestCase {

  /**
   * The group content enabler manager under test.
   *
   * @var \Drupal\group\Plugin\GroupContentEnablerManager
   */
  protected $groupContentEnablerManager;

  /**
   * The service container.
   *
   * @var \Symfony\Component\DependencyInjection\ContainerInterface|\Prophecy\Prophecy\ProphecyInterface
   */
  protected $container;

  /**
   * The plugin discovery.
   *
   * @var \Drupal\Component\Plugin\Discovery\DiscoveryInterface|\Prophecy\Prophecy\ProphecyInterface
   */
  protected $discovery;

  /**
   * The cache backend.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface|\Prophecy\Prophecy\ProphecyInterface
   */
  protected $cacheBackend;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface|\Prophecy\Prophecy\ProphecyInterface
   */
  protected $moduleHandler;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface|\Prophecy\Prophecy\ProphecyInterface
   */
  protected $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->cacheBackend = $this->prophesize(CacheBackendInterface::class);

    $this->moduleHandler = $this->prophesize(ModuleHandlerInterface::class);
    $this->moduleHandler->getImplementations('entity_type_build')->willReturn([]);
    $this->moduleHandler->alter('group_content_info', Argument::type('array'))->willReturn(NULL);

    $this->entityTypeManager = $this->prophesize(EntityTypeManagerInterface::class);
    $storage = $this->prophesize(ContentEntityStorageInterface::class);
    $this->entityTypeManager->getStorage('group')->willReturn($storage->reveal());
    $storage = $this->prophesize(ConfigEntityStorageInterface::class);
    $this->entityTypeManager->getStorage('group_type')->willReturn($storage->reveal());

    $this->groupContentEnablerManager = new TestGroupContentEnablerManager(new \ArrayObject(), $this->cacheBackend->reveal(), $this->moduleHandler->reveal(), $this->entityTypeManager->reveal());

    $this->discovery = $this->prophesize(DiscoveryInterface::class);
    $this->groupContentEnablerManager->setDiscovery($this->discovery->reveal());

    $this->container = $this->prophesize(ContainerInterface::class);
    $this->groupContentEnablerManager->setContainer($this->container->reveal());
  }

  /**
   * Sets up the group content enabler manager to be tested.
   *
   * @param array $definitions
   *   (optional) An array of group content enabler definitions.
   */
  protected function setUpPluginDefinitions($definitions = []) {
    $this->discovery->getDefinition(Argument::cetera())
      ->will(function ($args) use ($definitions) {
        $plugin_id = $args[0];
        $exception_on_invalid = $args[1];
        if (isset($definitions[$plugin_id])) {
          return $definitions[$plugin_id];
        }
        elseif (!$exception_on_invalid) {
          return NULL;
        }
        else {
          throw new PluginNotFoundException($plugin_id);
        }
      });
    $this->discovery->getDefinitions()->willReturn($definitions);
  }

  /**
   * Tests the hasHandler() method.
   *
   * @param string $plugin_id
   *   The ID of the plugin to check the handler for.
   * @param bool $expected
   *   Whether the handler is expected to be found.
   *
   * @covers ::hasHandler
   * @dataProvider providerTestHasHandler
   */
  public function testHasHandler($plugin_id, $expected) {
    $apple = ['handlers' => ['foo_handler' => TestGroupContentHandler::class]];
    $banana = ['handlers' => ['foo_handler' => FALSE]];
    $this->setUpPluginDefinitions(['apple' => $apple, 'banana' => $banana]);
    $this->assertSame($expected, $this->groupContentEnablerManager->hasHandler($plugin_id, 'foo_handler'));
  }

  /**
   * Provides test data for testHasHandler().
   *
   * @return array
   *   Test data.
   */
  public function providerTestHasHandler() {
    return [
      ['apple', TRUE],
      ['banana', FALSE],
      ['pear', FALSE],
    ];
  }

  /**
   * Tests the createHandlerInstance() method.
   *
   * @covers ::createHandlerInstance
   */
  public function testCreateHandlerInstance() {
    $handler = $this->groupContentEnablerManager->createHandlerInstance(TestGroupContentHandler::class, 'some_plugin', []);
    $this->assertInstanceOf(GroupContentHandlerBase::class, $handler);
    $this->assertInstanceOf(ModuleHandlerInterface::class, $handler->getModuleHandler());
  }

  /**
   * Tests exception thrown when a handler does not implement the interface.
   *
   * @covers ::createHandlerInstance
   */
  public function testCreateHandlerInstanceNoInterface() {
    $this->expectException(InvalidPluginDefinitionException::class);
    $this->expectExceptionMessage('Trying to instantiate a handler that does not implement \Drupal\group\Plugin\GroupContentHandlerInterface.');
    $this->groupContentEnablerManager->createHandlerInstance(TestGroupContentHandlerWithoutInterface::class, 'some_plugin', []);
  }

  /**
   * Tests the getHandler() method.
   *
   * @covers ::getHandler
   * @depends testCreateHandlerInstance
   */
  public function testGetHandler() {
    $apple = ['handlers' => ['foo_handler' => TestGroupContentHandler::class]];
    $this->setUpPluginDefinitions(['apple' => $apple]);

    $first_call_result = $this->groupContentEnablerManager->getHandler('apple', 'foo_handler');
    $second_call_result = $this->groupContentEnablerManager->getHandler('apple', 'foo_handler');
    $direct_call_result = $this->groupContentEnablerManager->createHandlerInstance($apple['handlers']['foo_handler'], 'apple', $apple);

    $this->assertEquals(
      $first_call_result,
      $direct_call_result,
      'Got the same result as if createHandlerInstance() were called directly.'
    );

    $this->assertSame(
      $first_call_result,
      $second_call_result,
      'Got the exact same handler instance when called another time.'
    );

    $this->assertNotSame(
      $first_call_result,
      $direct_call_result,
      'Calling createHandlerInstance() creates a fresh copy regardless of internal cache.'
    );
  }

  /**
   * Tests exception thrown when a plugin has not defined the requested handler.
   *
   * @covers ::getHandler
   */
  public function testGetHandlerMissingHandler() {
    $this->setUpPluginDefinitions(['apple' => ['handlers' => []]]);
    $this->expectException(InvalidPluginDefinitionException::class);
    $this->expectExceptionMessage('The "apple" plugin did not specify a foo_handler handler.');
    $this->groupContentEnablerManager->getHandler('apple', 'foo_handler');
  }

  /**
   * Tests the getAccessControlHandler() method.
   *
   * @covers ::getAccessControlHandler
   */
  public function testGetAccessControlHandler() {
    $apple = ['handlers' => ['access' => TestGroupContentHandler::class]];
    $this->setUpPluginDefinitions(['apple' => $apple]);
    $this->assertInstanceOf(GroupContentHandlerBase::class, $this->groupContentEnablerManager->getAccessControlHandler('apple'));
  }

  /**
   * Tests the getPermissionProvider() method.
   *
   * @covers ::getPermissionProvider
   */
  public function testGetPermissionProvider() {
    $apple = ['handlers' => ['permission_provider' => TestGroupContentHandler::class]];
    $this->setUpPluginDefinitions(['apple' => $apple]);
    $this->assertInstanceOf(GroupContentHandlerBase::class, $this->groupContentEnablerManager->getPermissionProvider('apple'));
  }

}

class TestGroupContentEnablerManager extends GroupContentEnablerManager {

  /**
   * Sets the discovery for the manager.
   *
   * @param \Drupal\Component\Plugin\Discovery\DiscoveryInterface $discovery
   *   The discovery object.
   */
  public function setDiscovery(DiscoveryInterface $discovery) {
    $this->discovery = $discovery;
  }

}

class TestGroupContentHandler extends GroupContentHandlerBase {

  /**
   * Returns the protected moduleHandler property.
   */
  public function getModuleHandler() {
    return $this->moduleHandler;
  }

}

class TestGroupContentHandlerWithoutInterface {

}
