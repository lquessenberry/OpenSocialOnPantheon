<?php

namespace Drupal\Tests\comment\Unit\Plugin\views\field;

use Drupal\comment\Plugin\views\field\LinkReply;
use Drupal\Core\Access\AccessManagerInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\views\Plugin\views\display\DisplayPluginBase;
use Drupal\views\ResultRow;
use Drupal\views\ViewExecutable;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * @coversDefaultClass \Drupal\comment\Plugin\views\field\LinkReply
 * @group comment
 */
class LinkReplyTest extends UnitTestCase {

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    parent::tearDown();
    $container = new ContainerBuilder();
    \Drupal::setContainer($container);
  }

  /**
   * @covers ::render
   */
  public function testRender() {
    $row = new ResultRow();
    $container = new ContainerBuilder();
    $container->set('string_translation', $this->createMock(TranslationInterface::class));
    \Drupal::setContainer($container);
    $field = new LinkReply([], '', [], $this->createMock(AccessManagerInterface::class), $this->createMock(EntityTypeManagerInterface::class), $this->createMock(EntityRepositoryInterface::class), $this->createMock(LanguageManagerInterface::class));
    $view = $this->createMock(ViewExecutable::class);
    $display = $this->createMock(DisplayPluginBase::class);
    $field->init($view, $display);
    $this->assertEmpty($field->render($row));
  }

}
