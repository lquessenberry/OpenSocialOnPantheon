<?php

namespace Drupal\ultimate_cron;

use Drupal\ultimate_cron\Entity\CronJob;

/**
 * Base class for tagged settings.
 *
 * Settings plugins using this as a base class, will only be available
 * to jobs having the same tag as the name of the plugin.
 */
class TaggedSettings extends Settings {
  /**
   * Only valid for jobs tagged with the proper tag.
   */
  public function isValid($job = NULL) {
    return $job ? in_array($this->name, $job->hook['tags']) : parent::isValid();
  }
}
