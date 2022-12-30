<?php

namespace Drupal\views_bulk_operations\Action;

use Drupal\Core\Action\ActionInterface;
use Drupal\views\ViewExecutable;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Defines Views Bulk Operations action interface.
 */
interface ViewsBulkOperationsActionInterface extends ActionInterface {

  /**
   * Set action context.
   *
   * Implementation should have an option to add data to the
   * context, not overwrite it on every method execution.
   *
   * @param array $context
   *   The context array.
   *
   * @see ViewsBulkOperationsActionBase::setContext
   */
  public function setContext(array &$context);

  /**
   * Set view object.
   *
   * @param \Drupal\views\ViewExecutable $view
   *   The processed view.
   */
  public function setView(ViewExecutable $view);

  /**
   * Execute action on multiple entities.
   *
   * Can return an array of results of processing, if no return value
   * is provided, action label will be used for each result.
   *
   * @param array $objects
   *   An array of entities.
   *
   * @return \Drupal\Component\Render\MarkupInterface[]
   *   An array of MarkupInterface objects or an empty array.
   */
  public function executeMultiple(array $objects);

  /**
   * Action batch execution finished callback.
   *
   * Used to set finished message, redirect or execute some final logic.
   *
   * @param bool $success
   *   Was the process successful?
   * @param array $results
   *   Batch process results array.
   * @param array $operations
   *   Performed operations array.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse|null
   *   Bach redirect response or NULL.
   */
  public static function finished($success, array $results, array $operations): ?RedirectResponse;

}
