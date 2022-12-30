<?php

namespace Drupal\update_helper\Events;

use Drupal\Component\EventDispatcher\Event;

/**
 * Event for command interactive.
 *
 * @package Drupal\update_helper\Events
 */
class CommandInteractEvent extends Event {

  /**
   * The command questions.
   *
   * @var array
   */
  protected $questions = [];

  /**
   * The collected variables.
   *
   * @var array
   */
  protected $vars;

  /**
   * Command interact event constructor.
   *
   * @param array $vars
   *   The collected vars.
   */
  public function __construct(array $vars) {
    $this->vars = $vars;
  }

  /**
   * The command questions.
   *
   * @return array
   *   All the questions.
   */
  public function getQuestions() {
    return $this->questions;
  }

  /**
   * Set the questions to ask.
   *
   * @param array $questions
   *   The array of questions.
   *
   * @return $this
   */
  public function setQuestions(array $questions) {
    $this->questions = $questions;
    return $this;
  }

  /**
   * Get the collected vars.
   *
   * @return array
   *   All the collected vars.
   */
  public function getVars() {
    return $this->vars;
  }

}
