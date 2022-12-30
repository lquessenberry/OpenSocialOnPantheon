<?php

namespace Drupal\Tests\views\Unit\Plugin\views\field;

use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\views\Plugin\views\display\DisplayPluginBase;
use Drupal\views\Plugin\views\field\RenderedEntity;
use Drupal\views\ResultRow;
use Drupal\views\ViewExecutable;

/**
 * @coversDefaultClass \Drupal\views\Plugin\views\field\RenderedEntity
 * @group Views
 */
class RenderedEntityTest extends UnitTestCase {

  /**
   * @covers ::render
   */
  public function testRender() {
    $row = new ResultRow();
    $field = new RenderedEntity([], '', [], $this->createMock(EntityTypeManagerInterface::class), $this->createMock(LanguageManagerInterface::class), $this->createMock(EntityRepositoryInterface::class), $this->createMock(EntityDisplayRepositoryInterface::class));
    $view = $this->createMock(ViewExecutable::class);
    $display = $this->createMock(DisplayPluginBase::class);
    $field->init($view, $display);
    $this->assertEmpty($field->render($row));
  }

}
