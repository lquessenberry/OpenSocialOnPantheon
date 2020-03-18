<?php

namespace Drupal\swiftmailer_test;

use Swift_Events_SendEvent;
use Swift_Events_SendListener;

class SwiftMailerDrupalStateLogger implements Swift_Events_SendListener {

  public function beforeSendPerformed(Swift_Events_SendEvent $evt) {
    $this->add([
      'method' => 'beforeSendPerformed',
      'body' => $evt->getMessage()->getBody(),
      'subject' => $evt->getMessage()->getSubject()
    ]);
  }

  public function sendPerformed(Swift_Events_SendEvent $evt) {
    $this->add([
      'method' => 'sendPerformed',
      'body' => $evt->getMessage()->getBody(),
      'subject' => $evt->getMessage()->getSubject()
    ]);
  }

  public function add($entry) {
    $captured_emails = \Drupal::state()->get('swiftmailer.mail_collector') ?: [];
    $captured_emails[] = $entry;
    \Drupal::state()->set('swiftmailer.mail_collector', $captured_emails);
  }

  public function clear() {
    \Drupal::state()->delete('swiftmailer.mail_collector');
  }

  public function dump() {
    return \Drupal::state()->get('swiftmailer.mail_collector', []);
  }

}
