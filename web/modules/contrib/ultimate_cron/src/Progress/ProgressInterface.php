<?php

namespace Drupal\ultimate_cron\Progress;

interface ProgressInterface {

  /**
   * Set job progress.
   *
   * @param string $job_id
   *   Cron Job id.
   *
   * @param float $progress
   *   The progress (0 - 1).
   */
  public function setProgress($job_id, $progress);

  /**
   * Get multiple job progresses.
   *
   * @param array $job_ids
   *   Job ids to get progress for.
   *
   * @return array
   *   Progress of jobs, keyed by job name.
   */
  public function getProgressMultiple($job_ids);

  /**
   * Get job progress.
   *
   * @param string $job_id
   *   Cron Job id.
   *
   * @return float
   *   The progress of this job.
   */
  public function getProgress($job_id);
}
