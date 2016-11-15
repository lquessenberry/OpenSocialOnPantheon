<?php

namespace Drupal\flag\Form;

use Drupal\Core\Entity\Query\QueryFactory;
use Drupal\Core\Database\Database;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Provides the Flag clear all form.
 */
class ClearAllForm extends ConfirmFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'flag_clearall_form';
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Clear all flag data?');
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return new Url('entity.flag.collection');
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return $this->t('This will delete all flaggings in preparation to uninstall Flag module. This operation cannot be undone.');
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return $this->t('Clear all');
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $batch = [
      'title' => $this->t('Clearing Flag data'),
      'operations' => [
        [
          [__CLASS__, 'resetFlags'], [],
        ],
        [
          [__CLASS__, 'clearTables'], [],
        ],
      ],
      'progress_message' => $this->t('Clearing Flag data...'),
    ];
    batch_set($batch);

    drupal_set_message($this->t(
      'Flag data has been cleared. <a href="@uninstall-url">Proceed with uninstallation.</a>',
      [
        '@uninstall-url' => Url::fromRoute('system.modules_uninstall')->toString(),
      ]
    ));
  }

  /**
   * Batch method to reset all flags.
   */
  public static function resetFlags(&$context) {
    // First, set the number of flags we'll process each invocation.
    $batch_size = 100;

    // If this is the first invocation, set our index and maximum.
    if (empty($context['sandbox'])) {
      $context['sandbox']['progress'] = 0;
      $context['sandbox']['max'] = \Drupal::entityQuery('flagging')->count()->execute();
    }

    // Get the next batch of flags to process.
    $query = \Drupal::entityQuery('flagging');
    $query->range($context['sandbox']['progress'], $batch_size);
    $ids = $query->execute();

    // Delete the flaggings.
    $storage = \Drupal::entityTypeManager()->getStorage('flagging');
    $storage->delete($storage->loadMultiple($ids));

    // Increment our progress.
    $context['sandbox']['progress'] += $batch_size;

    // If we still have flags to process, set our progress percentage.
    if ($context['sandbox']['progress'] < $context['sandbox']['max']) {
      $context['finished'] = $context['sandbox']['progress'] / $context['sandbox']['max'];
    }
  }

  /**
   * Batch method to truncate all remaining flag tables.
   */
  public static function clearTables(&$context) {
    // First, set the number of tables we'll truncate each invocation.

    $batch_size = 1;

    // Get the module schema.
    $schema = drupal_get_module_schema('flag');

    // If this is the first invocation, set our index and maximum.
    if (empty($context['sandbox'])) {
      $context['sandbox']['progress'] = 0;
      $context['sandbox']['max'] = count($schema);
    }

    // Get the database connection.
    $connection = Database::getConnection();

    // Truncate tables as needed in the batch.
    $tables = array_keys($schema);
    $progress = $context['sandbox']['progress'];
    for ($i = $progress; $i < $progress + $batch_size; $i++) {
      $table = $tables[$i];
      $connection->truncate($table)->execute();
    }

    // Increment our progress.
    $context['sandbox']['progress'] += $batch_size;

    // If we still have tables to truncate, set our progress percentage.
    if ($context['sandbox']['progress'] < $context['sandbox']['max']) {
      $context['finished'] = $context['sandbox']['progress'] / $context['sandbox']['max'];
    }
  }
}
