<?php

namespace Drupal\views_bulk_operations\Service;

use Drupal\Core\Access\AccessResultReasonInterface;
use Drupal\views\Views;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Component\Render\MarkupInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\views_bulk_operations\ViewsBulkOperationsBatch;
use Drupal\views_bulk_operations\Action\ViewsBulkOperationsActionInterface;

/**
 * Defines VBO action processor.
 */
class ViewsBulkOperationsActionProcessor implements ViewsBulkOperationsActionProcessorInterface {

  use StringTranslationTrait;

  /**
   * Maximum number of labels fetched for informational purposes.
   */
  const MAX_LIST_COUNT = 50;

  /**
   * View data provider service.
   *
   * @var \Drupal\views_bulk_operations\Service\ViewsbulkOperationsViewDataInterface
   */
  protected $viewDataService;

  /**
   * VBO action manager.
   *
   * @var \Drupal\views_bulk_operations\Service\ViewsBulkOperationsActionManager
   */
  protected $actionManager;

  /**
   * Current user object.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * Module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * Is the object initialized?
   *
   * @var bool
   */
  protected $initialized = FALSE;

  /**
   * Are we operating in exclude mode?
   *
   * @var bool
   */
  protected $excludeMode = FALSE;

  /**
   * The processed action object.
   *
   * @var array
   */
  protected $action;

  /**
   * The current view object.
   *
   * @var \Drupal\views\ViewExecutable
   */
  protected $view;

  /**
   * View data from the bulk form.
   *
   * @var array
   */
  protected $bulkFormData;

  /**
   * Array of entities that will be processed in the current batch.
   *
   * @var array
   */
  protected $queue = [];

  /**
   * Constructor.
   *
   * @param \Drupal\views_bulk_operations\Service\ViewsbulkOperationsViewDataInterface $viewDataService
   *   View data provider service.
   * @param \Drupal\views_bulk_operations\Service\ViewsBulkOperationsActionManager $actionManager
   *   VBO action manager.
   * @param \Drupal\Core\Session\AccountProxyInterface $currentUser
   *   Current user object.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   Module handler service.
   */
  public function __construct(
    ViewsbulkOperationsViewDataInterface $viewDataService,
    ViewsBulkOperationsActionManager $actionManager,
    AccountProxyInterface $currentUser,
    ModuleHandlerInterface $moduleHandler
  ) {
    $this->viewDataService = $viewDataService;
    $this->actionManager = $actionManager;
    $this->currentUser = $currentUser;
    $this->moduleHandler = $moduleHandler;
  }

  /**
   * {@inheritdoc}
   */
  public function initialize(array $view_data, $view = NULL): void {

    // It may happen that the service was already initialized
    // in this request (e.g. multiple Batch API operation calls).
    // Clear the processing queue in such a case.
    if ($this->initialized) {
      $this->queue = [];
    }

    $this->excludeMode = !empty($view_data['exclude_mode']);

    if (isset($view_data['action_id'])) {
      if (!isset($view_data['configuration'])) {
        $view_data['configuration'] = [];
      }
      if (!empty($view_data['preconfiguration'])) {
        $view_data['configuration'] += $view_data['preconfiguration'];
      }

      // Initialize action object.
      $this->action = $this->actionManager->createInstance($view_data['action_id'], $view_data['configuration']);

      // Set action context.
      $this->setActionContext($view_data);
    }

    // Set entire view data as object parameter for future reference.
    $this->bulkFormData = $view_data;

    // Set the current view.
    $this->setView($view);

    $this->initialized = TRUE;
  }

  /**
   * Set the current view object.
   *
   * @param mixed $view
   *   The current view object or NULL.
   */
  protected function setView($view = NULL): void {
    if (!is_null($view)) {
      $this->view = $view;
    }
    else {
      $this->view = Views::getView($this->bulkFormData['view_id']);
      $this->view->setDisplay($this->bulkFormData['display_id']);
    }
    $this->view->get_total_rows = TRUE;
    $this->view->views_bulk_operations_processor_built = TRUE;
    if (!empty($this->bulkFormData['arguments'])) {
      $this->view->setArguments($this->bulkFormData['arguments']);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getLabels(array $view_data) {
    $this->initialize($view_data);

    // We don't want to load too many entities here due to performance reasons.
    if (count($view_data['list']) > self::MAX_LIST_COUNT) {
      $view_data['list'] = array_slice($view_data['list'], 0, self::MAX_LIST_COUNT);
    }

    $this->populateQueue($view_data);

    $labels = [];
    foreach ($this->queue as $entity) {
      $labels[] = $entity->label();
    }
    return $labels;
  }

  /**
   * {@inheritdoc}
   */
  public function getPageList($page) {
    $list = [];

    $this->viewDataService->init($this->view, $this->view->getDisplay(), $this->bulkFormData['relationship_id']);

    // Set exposed filters and pager parameters.
    if (!empty($this->bulkFormData['clear_on_exposed']) && !empty($this->bulkFormData['exposed_input'])) {
      $this->view->setExposedInput($this->bulkFormData['exposed_input']);
    }
    else {
      $this->view->setExposedInput(['_views_bulk_operations_override' => TRUE]);
    }

    $base_field = $this->view->storage->get('base_field');

    // In some cases we may encounter nondeterministic behaviour in
    // db queries with sorts allowing different order of results.
    // To fix this we're removing all sorts and setting one sorting
    // rule by the view base id field.
    foreach (array_keys($this->view->getHandlers('sort')) as $id) {
      $this->view->setHandler($this->bulkFormData['display_id'], 'sort', $id, NULL);
    }
    $this->view->setHandler($this->bulkFormData['display_id'], 'sort', $base_field, [
      'id' => $base_field,
      'table' => $this->view->storage->get('base_table'),
      'field' => $base_field,
      'order' => 'ASC',
      'relationship' => 'none',
      'group_type' => 'group',
      'exposed' => FALSE,
      'plugin_id' => 'standard',
    ]);

    $this->view->setItemsPerPage($this->bulkFormData['batch_size']);
    $this->view->setCurrentPage($page);
    $this->view->build();

    $offset = $this->bulkFormData['batch_size'] * $page;
    // If the view doesn't start from the first result,
    // move the offset.
    if ($pager_offset = $this->view->pager->getOffset()) {
      $offset += $pager_offset;
    }
    $this->view->query->setLimit($this->bulkFormData['batch_size']);
    $this->view->query->setOffset($offset);
    $this->moduleHandler->invokeAll('views_pre_execute', [$this->view]);
    $this->view->query->execute($this->view);

    foreach ($this->view->result as $row) {
      $entity = $this->viewDataService->getEntity($row);

      $exclude = FALSE;
      if ($this->excludeMode) {
        // Filter out excluded results basing on base field ID and language.
        foreach ($this->bulkFormData['exclude_list'] as $key => $item) {
          if ($row->{$base_field} === $item[0] && $entity->language()->getId() === $item[1]) {
            $exclude = TRUE;
            break;
          }
        }
      }

      if (!$exclude) {
        $list[] = [
          $row->{$base_field},
          $entity->language()->getId(),
          $entity->getEntityTypeId(),
          $entity->id(),
        ];
      }
    }

    return $list;
  }

  /**
   * {@inheritdoc}
   */
  public function populateQueue(array $data, array &$context = []) {
    $list = $data['list'];
    $base_field = $this->view->storage->get('base_field');
    $this->queue = [];

    // Determine batch size and offset.
    if (!empty($context)) {
      $batch_size = $data['batch_size'];
      if (!isset($context['sandbox']['current_batch'])) {
        $context['sandbox']['current_batch'] = 0;
      }
      $current_batch = &$context['sandbox']['current_batch'];
      $offset = $current_batch * $batch_size;
    }
    else {
      $batch_size = 0;
      $current_batch = 0;
      $offset = 0;
    }

    if ($batch_size) {
      $batch_list = array_slice($list, $offset, $batch_size);
    }
    else {
      $batch_list = $list;
    }

    // Note: this needs to be set to 0 because otherwise we may lose
    // entity translations from the results.
    $this->view->setItemsPerPage(0);
    $this->view->setCurrentPage(0);
    $this->view->setOffset(0);
    $this->view->initHandlers();
    $this->view->setExposedInput(['_views_bulk_operations_override' => TRUE]);

    // Remove all exposed filters so we don't have any default filter
    // values that could make the actual selection out of range.
    if (!empty($this->view->filter)) {
      foreach ($this->view->filter as $id => $filter) {
        if (!empty($filter->options['exposed'])) {
          unset($this->view->filter[$id]);
        }
      }
    }

    // Build the view query.
    $this->view->build();

    // Modify the view query: determine and apply the base field condition.
    $base_field_values = [];
    foreach ($batch_list as $item) {
      $base_field_values[$item[0]] = $item[0];
    }
    if (empty($base_field_values)) {
      return 0;
    }

    if (isset($this->view->query->fields[$base_field])) {
      if (!empty($this->view->query->fields[$base_field]['table'])) {
        $base_field_alias = $this->view->query->fields[$base_field]['table'] . '.' . $this->view->query->fields[$base_field]['alias'];
      }
      else {
        $base_field_alias = $this->view->query->fields[$base_field]['alias'];
      }
    }
    else {
      $base_field_alias = $base_field;
    }

    $this->view->query->addWhere(0, $base_field_alias, $base_field_values, 'IN');

    // Rebuild the view query.
    $this->view->query->build($this->view);

    // We just destroyed any metadata that other modules may have added to the
    // query. Give those modules the opportunity to alter the query again.
    $this->view->query->alter($this->view);

    // Use a different pager ID so we don't break the real pager.
    // @todo Check if we can use something else to set this value.
    $pager = $this->view->getPager();
    if (array_key_exists('id', $pager->options)) {
      $pager->options['id'] += (1000 + $this->view->getItemsPerPage());
    }

    // Execute the view.
    $this->moduleHandler->invokeAll('views_pre_execute', [$this->view]);
    $this->view->query->execute($this->view);

    // Get entities.
    $this->viewDataService->init($this->view, $this->view->getDisplay(), $this->bulkFormData['relationship_id']);

    // Get all the entities in the batch_list from the view.
    // Check labnguage as well as the query will fetch results basing on
    // base ID field for all languages.
    $result_hits = [];
    foreach ($batch_list as $delta => $item) {
      foreach ($this->view->result as $row_index => $row) {
        if (array_key_exists($row_index, $result_hits)) {
          continue;
        }
        $entity = $this->viewDataService->getEntity($row);
        if ($row->{$base_field} === $item[0] && $entity->language()->getId() === $item[1]) {
          $result_hits[$row_index] = TRUE;
          $this->queue[] = $entity;
          break;
        }
      }
    }

    // Extra processing when executed in a Batch API operation.
    if (!empty($context)) {
      if (!isset($context['sandbox']['total'])) {
        if (empty($list)) {
          $context['sandbox']['total'] = $this->viewDataService->getTotalResults($data['clear_on_exposed']);
        }
        else {
          $context['sandbox']['total'] = count($list);
        }
      }
      // Add batch size to context array for potential use in actions.
      $context['sandbox']['batch_size'] = $batch_size;
      $this->setActionContext($context);
    }

    if ($batch_size) {
      $current_batch++;
    }

    $this->setActionView();

    return count($this->queue);
  }

  /**
   * Set action context if action method exists.
   *
   * @param array $context
   *   The context to be set.
   */
  protected function setActionContext(array $context) {
    if (isset($this->action) && method_exists($this->action, 'setContext')) {
      $this->action->setContext($context);
    }
  }

  /**
   * Sets the current view object as the executed action parameter.
   */
  protected function setActionView() {
    if (isset($this->action) && method_exists($this->action, 'setView')) {
      $this->action->setView($this->view);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function process() {
    $output = [];

    // Check if all queue items are actually Drupal entities.
    foreach ($this->queue as $delta => $entity) {
      if (!($entity instanceof EntityInterface)) {
        $output[] = $this->t('Skipped');
        unset($this->queue[$delta]);
      }
    }

    // Check entity type for multi-type views like search_api index.
    $action_definition = $this->actionManager->getDefinition($this->bulkFormData['action_id']);
    if (!empty($action_definition['type'])) {
      foreach ($this->queue as $delta => $entity) {
        if ($entity->getEntityTypeId() !== $action_definition['type']) {
          $output[] = $this->t('Entity type not supported');
          unset($this->queue[$delta]);
        }
      }
    }

    // Check access.
    foreach ($this->queue as $delta => $entity) {
      $accessResult = $this->action->access($entity, $this->currentUser, TRUE);
      if ($accessResult->isAllowed() === FALSE) {
        $message = $this->t('Access denied');

        // If we're given a reason why access was denied, display it.
        if ($accessResult instanceof AccessResultReasonInterface) {
          $reason = $accessResult->getReason();
          if (!empty($reason)) {
            $message = $this->t('Access denied: @reason', [
              '@reason' => $accessResult->getReason(),
            ]);
          }
        }

        $output[] = $message;
        unset($this->queue[$delta]);
      }
    }

    // Process queue.
    $results = $this->action->executeMultiple($this->queue);

    // Prepare for the next major change: type hinting.
    if ($this->action instanceof ViewsBulkOperationsActionInterface) {
      $deprecated = FALSE;
      if (!is_array($results)) {
        $deprecated = TRUE;
      }
      else {
        foreach ($results as $result) {
          if (!$result instanceof MarkupInterface) {
            $deprecated = TRUE;
            break;
          }
        }
      }
      if ($deprecated) {
        @trigger_error(sprintf('The executeMultiple method of the %s class must return an array of \Drupal\Component\Render\MarkupInterface, other return types are deprecated.', E_USER_DEPRECATED));
      }
    }

    // Populate output.
    if (empty($results)) {
      $count = count($this->queue);
      for ($i = 0; $i < $count; $i++) {
        $output[] = $this->bulkFormData['action_label'];
      }
      return $output;
    }
    return array_merge($output, $results);
  }

  /**
   * {@inheritdoc}
   */
  public function executeProcessing(array &$data, $view = NULL) {
    if (empty($data['prepopulated']) && $data['exclude_mode'] && empty($data['exclude_list'])) {
      $data['exclude_list'] = $data['list'];
      $data['list'] = [];
    }

    // Get action finished callable.
    $definition = $this->actionManager->getDefinition($data['action_id']);
    if (in_array(ViewsBulkOperationsActionInterface::class, class_implements($definition['class']), TRUE)) {
      $data['finished_callback'] = [$definition['class']];
    }
    else {
      $data['finished_callback'] = [ViewsBulkOperationsBatch::class];
    }
    $data['finished_callback'][] = 'finished';

    if ($data['batch']) {
      $batch = ViewsBulkOperationsBatch::getBatch($data);
      batch_set($batch);
    }
    else {
      // Populate and process queue.
      $this->initialize($data, $view);
      if (empty($data['list'])) {
        $data['list'] = $this->getPageList(0);
      }
      if ($this->populateQueue($data)) {
        $batch_results = $this->process();
      }

      $results = ['operations' => []];
      foreach ($batch_results as $result) {
        $results['operations'][] = (string) $result;
      }
      $data['finished_callback'](TRUE, $results, []);
    }
  }

}
