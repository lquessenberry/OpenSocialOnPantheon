<?php

namespace Drupal\profile\Event;

use Drupal\profile\Entity\ProfileInterface;
use Symfony\Component\EventDispatcher\Event;

/**
 * Defines the profile label event.
 *
 * @see \Drupal\address\Event\AddressEvents
 */
class ProfileLabelEvent extends Event {

  /**
   * The profile.
   *
   * @var \Drupal\profile\Entity\ProfileInterface
   */
  protected $profile;

  /**
   * The label.
   *
   * @var string
   */
  protected $label;

  /**
   * Constructs a new ProfileLabelEvent object.
   *
   * @param \Drupal\profile\Entity\ProfileInterface $profile
   *   The profile.
   * @param string $label
   *   The profile label.
   */
  public function __construct(ProfileInterface $profile, $label) {
    $this->profile = $profile;
    $this->label = $label;
  }

  /**
   * Gets the profile.
   *
   * @return \Drupal\profile\Entity\ProfileInterface
   *   The profile.
   */
  public function getProfile() {
    return $this->profile;
  }

  /**
   * Gets the profile label.
   *
   * @return string
   *   The profile label.
   */
  public function getLabel() {
    return $this->label;
  }

  /**
   * Sets the profile label.
   *
   * @param string $label
   *   The profile label.
   *
   * @return $this
   */
  public function setLabel($label) {
    $this->label = $label;
    return $this;
  }

}
