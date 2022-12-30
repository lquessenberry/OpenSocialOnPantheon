<?php

namespace Drupal\ginvite\Event;

use Drupal\ginvite\GroupInvitation;
use Symfony\Component\EventDispatcher\Event;

/**
 * Event related with user registered invitation.
 *
 * @package Drupal\ginvite\Event
 */
class UserRegisteredFromInvitationEvent extends Event {

  const EVENT_NAME = 'user_registered_from_invitation';

  /**
   * The group invitation.
   *
   * @var \Drupal\ginvite\GroupInvitation
   */
  protected $groupInvitation;

  /**
   * Constructs the object.
   *
   * @param \Drupal\ginvite\GroupInvitation $group_invitation
   *   The group invitation.
   */
  public function __construct(GroupInvitation $group_invitation) {
    $this->groupInvitation = $group_invitation;
  }

  /**
   * Get the group invitation.
   *
   * @return \Drupal\ginvite\GroupInvitation
   *   The group invitation.
   */
  public function getGroupInvitation(): GroupInvitation {
    return $this->groupInvitation;
  }

}
