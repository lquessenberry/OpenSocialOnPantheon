<?php

namespace Drupal\Tests\flag\Unit\Ajax;

use Drupal\flag\Ajax\ActionLinkFlashCommand;
use Drupal\Tests\UnitTestCase;

/**
 * @coversDefaultClass \Drupal\flag\Ajax\ActionLinkFlashCommand
 * @group flag
 */
class ActionLinkFlashCommandTest extends UnitTestCase {

  /**
   * The Random Utility.
   *
   * @var \Drupal\Component\Utility\Random
   */
  protected $random;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    $this->random = $this->getRandomGenerator();
  }

  /**
   * @covers ::render
   */
  public function testRender() {

    // The first two characters of the CSS selector must be of the form
    //  '\.[a-z A-Z]'.
    $selector = '.' . $this->random->name(10, TRUE);

    $message = $this->random->string(100, TRUE);

    $command = new ActionLinkFlashCommand($selector, $message);

    $expected = [
      'command' => 'actionLinkFlash',
      'selector' => $selector,
      'message' => $message,
    ];
    $this->assertEquals($expected, $command->render(), 'The command was created as expected ');
  }

}
