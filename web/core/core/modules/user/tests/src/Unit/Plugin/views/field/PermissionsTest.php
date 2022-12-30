<?php

namespace Drupal\Tests\user\Unit\Plugin\views\field;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\user\PermissionHandlerInterface;
use Drupal\user\Plugin\views\field\Permissions;
use Drupal\views\Plugin\views\display\DisplayPluginBase;
use Drupal\views\ResultRow;
use Drupal\views\ViewExecutable;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * @coversDefaultClass \Drupal\user\Plugin\views\field\Permissions
 * @group user
 */
class PermissionsTest extends UnitTestCase {

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    parent::tearDown();
    $container = new ContainerBuilder();
    \Drupal::setContainer($container);
  }

  /**
   * Tests the preRender method.
   *
   * @covers ::preRender
   */
  public function testPreRender() {
    $values = [new ResultRow()];
    $container = new ContainerBuilder();
    $container->set('string_translation', $this->createMock(TranslationInterface::class));
    $container->set('user.permissions', $this->createMock(PermissionHandlerInterface::class));
    \Drupal::setContainer($container);
    $field = new Permissions([], '', [], $this->createMock(ModuleHandlerInterface::class), $this->createMock(EntityTypeManagerInterface::class));
    $view = $this->createMock(ViewExecutable::class);
    $display = $this->createMock(DisplayPluginBase::class);
    $field->init($view, $display);
    $this->assertEquals('', $field->preRender($values));
  }

}
