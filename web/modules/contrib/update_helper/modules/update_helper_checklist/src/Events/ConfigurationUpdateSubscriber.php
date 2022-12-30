<?php

namespace Drupal\update_helper_checklist\Events;

use Drupal\update_helper\Events\ConfigurationUpdateEvent;
use Drupal\update_helper\Events\UpdateHelperEvents;
use Drupal\update_helper_checklist\UpdateChecklist;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Configuration update subscriber.
 *
 * @package Drupal\update_helper_checklist\Events
 */
class ConfigurationUpdateSubscriber implements EventSubscriberInterface {

  /**
   * Update checklist service.
   *
   * @var \Drupal\update_helper_checklist\UpdateChecklist
   */
  protected $updateChecklist;

  /**
   * ConfigurationUpdateSubscriber constructor.
   *
   * @param \Drupal\update_helper_checklist\UpdateChecklist $update_checklist
   *   Update checklist service.
   */
  public function __construct(UpdateChecklist $update_checklist) {
    $this->updateChecklist = $update_checklist;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      UpdateHelperEvents::CONFIGURATION_UPDATE => [
        ['onConfigurationUpdate', 10],
      ],
    ];
  }

  /**
   * Handles on configuration update event.
   *
   * @param \Drupal\update_helper\Events\ConfigurationUpdateEvent $event
   *   Configuration update event.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function onConfigurationUpdate(ConfigurationUpdateEvent $event) {
    if ($event->isSuccessful()) {
      $this->updateChecklist->markUpdatesSuccessful([$event->getModule() => [$event->getUpdateName()]]);
    }
    else {
      $this->updateChecklist->markUpdatesFailed([$event->getModule() => [$event->getUpdateName()]]);
    }
  }

}
