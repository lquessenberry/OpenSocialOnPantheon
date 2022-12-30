<?php

namespace Drupal\Tests\comment\Unit\Plugin\views\field;

use Drupal\comment\Plugin\views\field\EntityLink;
use Drupal\Tests\UnitTestCase;
use Drupal\views\Plugin\views\display\DisplayPluginBase;
use Drupal\views\ResultRow;
use Drupal\views\ViewExecutable;

/**
 * @coversDefaultClass \Drupal\comment\Plugin\views\field\EntityLink
 * @group comment
 */
class EntityLinkTest extends UnitTestCase {

  /**
   * Test the render method.
   *
   * @covers ::render
   */
  public function testRender() {
    $row = new ResultRow();
    $field = new EntityLink([], '', []);
    $view = $this->createMock(ViewExecutable::class);
    $display = $this->createMock(DisplayPluginBase::class);
    $field->init($view, $display);
    $this->assertEmpty($field->render($row));
  }

}
