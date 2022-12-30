<?php

namespace Drupal\views_bulk_operations\Action;

use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Defines action completion logic.
 */
trait ViewsBulkOperationsActionCompletedTrait {

  /**
   * Set message function wrapper.
   *
   * @see \Drupal\Core\Messenger\MessengerInterface
   */
  public static function message($message = NULL, $type = 'status', $repeat = TRUE) {
    \Drupal::messenger()->addMessage($message, $type, $repeat);
  }

  /**
   * Translation function wrapper.
   *
   * @see \Drupal\Core\StringTranslation\TranslationInterface:translate()
   */
  public static function translate($string, array $args = [], array $options = []) {
    return \Drupal::translation()->translate($string, $args, $options);
  }

  /**
   * Batch finished callback.
   *
   * @param bool $success
   *   Was the process successful?
   * @param array $results
   *   Batch process results array.
   * @param array $operations
   *   Performed operations array.
   */
  public static function finished($success, array $results, array $operations): ?RedirectResponse {
    if ($success) {
      $operations = array_count_values($results['operations']);
      $details = [];
      foreach ($operations as $op => $count) {
        $details[] = $op . ' (' . $count . ')';
      }
      $message = static::translate('Action processing results: @operations.', [
        '@operations' => implode(', ', $details),
      ]);
      static::message($message);
    }
    else {
      $message = static::translate('Finished with an error.');
      static::message($message, 'error');
    }
    return NULL;
  }

}
