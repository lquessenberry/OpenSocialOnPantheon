<?php

namespace Drupal\update_helper_checklist;

use Drupal\checklistapi\Storage\StateStorage;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\update_helper_checklist\Entity\Update;
use Symfony\Component\Yaml\Yaml;

/**
 * Update checklist service.
 *
 * TODO: Need tests and a lot!
 *
 * @package Drupal\update_helper_checklist
 */
class UpdateChecklist {

  /**
   * Update checklist file for configuration updates.
   *
   * @var string
   */
  public static $updateChecklistFileName = 'updates_checklist.yml';

  /**
   * Site checklist state storage service.
   *
   * @var \Drupal\checklistapi\Storage\StateStorage
   */
  protected $checkListStateStorage;

  /**
   * Module installer service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The account object.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $account;

  /**
   * The Checklist API object.
   *
   * @var \Drupal\checklistapi\ChecklistapiChecklist
   */
  protected $checklist;

  /**
   * Update checklist constructor.
   *
   * @param \Drupal\checklistapi\Storage\StateStorage $stateStorage
   *   The check list state storage service.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   Module handler service.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The current user.
   */
  public function __construct(StateStorage $stateStorage, ModuleHandlerInterface $moduleHandler, AccountInterface $account) {
    $this->checkListStateStorage = $stateStorage;
    $this->moduleHandler = $moduleHandler;
    $this->account = $account;
  }

  /**
   * Get checklist.
   *
   * @return \Drupal\checklistapi\ChecklistapiChecklist|false
   *   Returns checklist.
   */
  protected function getChecklist() {
    if (!$this->checklist) {
      $this->checklist = checklistapi_checklist_load('update_helper_checklist');
    }

    return $this->checklist;
  }

  /**
   * Marks a list of updates as successful.
   *
   * @param array $module_updates
   *   Array of update ids per module.
   * @param bool $checkListPoints
   *   Indicates the corresponding checkbox should be checked.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function markUpdatesSuccessful(array $module_updates, $checkListPoints = TRUE) {
    $update_ids = $this->getFlatChecklistKeys($module_updates);

    $this->setSuccessfulByHook($update_ids, TRUE);

    if ($checkListPoints) {
      $this->checkListPoints($update_ids);
    }
  }

  /**
   * Marks a list of updates as failed.
   *
   * @param array $module_updates
   *   Array of update ids per module.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function markUpdatesFailed(array $module_updates) {
    $update_ids = $this->getFlatChecklistKeys($module_updates);

    $this->setSuccessfulByHook($update_ids, FALSE);
  }

  /**
   * Marks a list of updates.
   *
   * @param bool $status
   *   Checkboxes enabled or disabled.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function markAllUpdates($status = TRUE) {
    $update_ids = [];
    foreach ($this->getChecklist()->items as $version_items) {
      foreach ($version_items as $key => $item) {
        if (is_array($item)) {
          $update_ids[] = $key;
        }
      }
    }

    $this->setSuccessfulByHook($update_ids, $status);
    $this->checkAllListPoints($status);
  }

  /**
   * Set status for update keys.
   *
   * @param array $update_ids
   *   Keys for update entries.
   * @param bool $status
   *   Status that should be set.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function setSuccessfulByHook(array $update_ids, $status = TRUE) {
    foreach ($update_ids as $update_id) {
      if ($update = Update::load($update_id)) {
        $update->setSuccessfulByHook($status);
        $update->save();
      }
      else {
        Update::create(
          [
            'id' => $update_id,
            'successful_by_hook' => $status,
          ]
        )->save();
      }
    }
  }

  /**
   * Get flat list of checklist keys for module updates.
   *
   * @param array $module_update_list
   *   Keys for update entries per module.
   *
   * @return array
   *   Returns flattened array of checklist update entries.
   */
  protected function getFlatChecklistKeys(array $module_update_list) {
    $flatKeys = [];

    foreach ($module_update_list as $module_name => $updates) {
      foreach ($updates as $update) {
        $flatKeys[] = str_replace('.', '_', $module_name . ':' . $update);
      }
    }

    return $flatKeys;
  }

  /**
   * Checks an array of bulletpoints on a checklist.
   *
   * @param array $update_ids
   *   Array of the bulletpoints.
   */
  protected function checkListPoints(array $update_ids) {
    $current_progress = $this->checkListStateStorage->setChecklistId('update_helper_checklist')->getSavedProgress();

    $user = $this->account->id();
    $time = time();

    foreach ($update_ids as $update_id) {
      if (empty($current_progress['#items'][$update_id])) {
        $current_progress['#items'][$update_id] = [
          '#completed' => time(),
          '#uid' => $user,
        ];
      }
    }

    $current_progress['#completed_items'] = count($current_progress['#items']);
    $current_progress['#changed'] = $time;
    $current_progress['#changed_by'] = $user;

    $this->checkListStateStorage->setChecklistId('update_helper_checklist')->setSavedProgress($current_progress);
  }

  /**
   * Checks all the bulletpoints on a checklist.
   *
   * @param bool $status
   *   Checkboxes enabled or disabled.
   */
  protected function checkAllListPoints($status = TRUE) {
    $current_progress = $this->checkListStateStorage->setChecklistId('update_helper_checklist')->getSavedProgress();

    $user = $this->account->id();
    $time = time();

    $current_progress['#changed'] = $time;
    $current_progress['#changed_by'] = $user;

    $exclude = [
      '#title',
      '#description',
      '#weight',
    ];

    foreach ($this->getChecklist()->items as $version_items) {
      foreach ($version_items as $item_name => $item) {
        if (!in_array($item_name, $exclude)) {
          if ($status) {
            $current_progress['#items'][$item_name] = [
              '#completed' => $time,
              '#uid' => $user,
            ];
          }
          else {
            unset($current_progress['#items'][$item_name]);
          }
        }
      }
    }

    $current_progress['#completed_items'] = empty($current_progress['#items']) ? 0 : count($current_progress['#items']);

    $this->checkListStateStorage->setChecklistId('update_helper_checklist')->setSavedProgress($current_progress);
  }

  /**
   * Get update version from update checklist file.
   *
   * @param string $module
   *   Module name.
   *
   * @return array
   *   Returns update versions from update checklist file.
   */
  public function getUpdateVersions($module) {
    $module_directories = $this->moduleHandler->getModuleDirectories();

    if (empty($module_directories[$module])) {
      return [];
    }

    $updates_file = $module_directories[$module] . DIRECTORY_SEPARATOR . static::$updateChecklistFileName;
    if (!is_file($updates_file)) {
      return [];
    }

    $updates_checklist = Yaml::parse(file_get_contents($updates_file));

    return array_keys($updates_checklist);
  }

}
