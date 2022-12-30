<?php

namespace Drupal\ultimate_cron\Progress;

use Drupal\Core\KeyValueStore\KeyValueFactory;
use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;

class Progress implements ProgressInterface {
  protected $progressUpdated = 0;
  protected $interval = 1;

  /**
   * @var \Drupal\Core\KeyValueStore\KeyValueStoreInterface
   */
  protected $keyValue;

  /**
   * Constructor.
   *
   * @param float $interval
   *   How often the database should be updated with the progress.
   */
  public function __construct(KeyValueFactoryInterface $key_value_factory, $interval = 1) {
    $this->keyValue = $key_value_factory->get('uc-progress');
    $this->interval = $interval;
  }

  /**
   * {@inheritdoc}
   */
  public function getProgress($job_id) {
    $value = $this->keyValue->get($job_id);
    return $value;
  }

  /**
   * {@inheritdoc}
   */
   public function getProgressMultiple($job_ids) {
    $values = $this->keyValue->getMultiple($job_ids);

    return $values;
  }

  /**
   * {@inheritdoc}
   */
  public function setProgress($job_id, $progress) {
    if (microtime(TRUE) >= $this->progressUpdated + $this->interval) {
      $this->keyValue->set($job_id, $progress);

      $this->progressUpdated = microtime(TRUE);
      return TRUE;
    }
    return FALSE;
  }
}
