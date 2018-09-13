<?php

namespace Drupal\r4032login\Event;

use Symfony\Component\EventDispatcher\Event;

/**
 * Event that is fired just before the redirection is perform.
 */
class RedirectEvent extends Event {

  const EVENT_NAME = 'r4032login.redirect';

  /**
   * The redirect url.
   *
   * @var string
   */
  private $url;

  /**
   * The redirect options.
   *
   * @var array
   */
  private $options;

  /**
   * Constructs the object.
   *
   * @param string $url
   *   The redirect url.
   * @param array $options
   *   The redirect options.
   */
  public function __construct($url, array $options) {
    $this->url = $url;
    $this->options = $options;
  }

  /**
   * Getter for url property.
   *
   * @return string
   *   The redirection url.
   */
  public function getUrl() {
    return $this->url;
  }

  /**
   * Setter for url property.
   *
   * @param string $url
   *   The url to redirect to.
   */
  public function setUrl($url) {
    $this->url = $url;
  }

  /**
   * Getter for options property.
   *
   * @return array
   *   The url redirection options.
   */
  public function getOptions() {
    return $this->options;
  }

  /**
   * Setter for options property.
   *
   * @param array $options
   *   The url options.
   */
  public function setOptions(array $options) {
    $this->options = $options;
  }

}
