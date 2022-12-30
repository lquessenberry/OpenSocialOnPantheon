<?php

namespace Drupal\ultimate_cron;

use Drupal\Core\Config\Entity\DraggableListBuilder;
use Drupal\Core\Entity\EntityInterface;

/**
 * Defines a class to build a listing of cron jobs.
 *
 * @see \Drupal\ultimate_cron\Entity\CronJob
 */
class CronJobListBuilder extends DraggableListBuilder {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'ultimate_cron_job_list';
  }

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header = array();
    $header['label'] = array('data' => t('Title'));
    $header['module'] = array('data' => t('Module'));
    $header['scheduled'] = array('data' => t('Scheduled'));
    $header['started'] = array('data' => t('Last Run'));
    $header['duration'] = array('data' => t('Duration'));
    $header['status'] = array('data' => t('Status'));
    return $header + parent::buildHeader();
  }
  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /* @var \Drupal\ultimate_cron\CronJobInterface $entity */
    $icon = \Drupal::service('extension.list.module')->getPath('ultimate_cron') . '/icons/hourglass.png';
    $behind_icon = ['#prefix' => ' ', '#theme' => 'image', '#uri' => \Drupal::service('file_url_generator')->generateAbsoluteString($icon), '#title' => t('Job is behind schedule!')];

    $log_entry = $entity->loadLatestLogEntry();
    $row['label'] = $entity->label();
    $row['module']['#markup'] = $entity->getModuleName();
    $row['module']['#wrapper_attributes']['title'] = $entity->getModuleDescription();
    $row['scheduled']['label']['#markup'] = $entity->getPlugin('scheduler')->formatLabel($entity);
    if ($entity->isScheduled()) {
      $row['scheduled']['behind'] = $behind_icon;
    }
    // If the start time is 0, the jobs have never been run.
    $row['started']['#markup'] = $log_entry->start_time ? \Drupal::service('date.formatter')->format((int) $log_entry->start_time, "short") : $this->t('Never');

    // Display duration
    $progress = $entity->isLocked() ? $entity->formatProgress() : '';
    $row['duration'] = [
      '#markup' => '<span class="duration-time" data-src="' . $log_entry->getDuration() . '">' . $log_entry->formatDuration() . '</span> <span class="duration-progress">' . $progress . '</span>',
      '#wrapper_attributes' => ['title' => $log_entry->formatEndTime()],
     ];

    if (!$entity->isValid()) {
      $row['status']['#markup'] = $this->t('Missing');
    }
    elseif (!$entity->status()) {
      $row['status']['#markup'] = $this->t('Disabled');
    }
    else {
      // Get the status from the launcher when running, otherwise use the last
      // log entry.
      if ($entity->isLocked() && $log_entry->lid == $entity->isLocked()) {
        list($status, $title) = $entity->getPlugin('launcher')->formatRunning($entity);
      }
      elseif ($log_entry->start_time && !$log_entry->end_time) {
        list($status, $title) = $entity->getPlugin('launcher')->formatUnfinished($entity);
      }
      else {
        list($status, $title) = $log_entry->formatSeverity();
        $title = $log_entry->message ? $log_entry->message : $title;
      }

      $row['status'] = $status;
      $row['status']['#wrapper_attributes']['title'] = $title;
    }

    $row += parent::buildRow($entity);
    $row['weight']['#delta'] = 50;
    return $row;
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultOperations(EntityInterface $entity) {
    $operations = parent::getDefaultOperations($entity);
    if ($entity->status() && $entity->isValid()) {
      if (!$entity->isLocked()) {
        $operations += [
          'run' => [
            'title' => t('Run'),
            'weight' => 9,
            'url' => $entity->toUrl('run'),
          ]
        ];
      }
      else {
        $operations += [
          'unlock' => [
            'title' => t('Unlock'),
            'weight' => 9,
            'url' => $entity->toUrl('unlock'),
          ]
        ];
      }
    }

    $operations += [
      'logs' => [
        'title' => t('Logs'),
        'weight' => 10,
        'url' => $entity->toUrl('logs'),
      ],
    ];

    // Invalid jobs can not be enabled nor disabled.
    if (!$entity->isValid()) {
      unset($operations['disable']);
      unset($operations['enable']);
    }

    return $operations;
  }

}
