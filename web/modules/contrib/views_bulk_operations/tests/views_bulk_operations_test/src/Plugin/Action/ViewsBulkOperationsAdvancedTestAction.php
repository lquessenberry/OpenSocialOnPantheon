<?php

namespace Drupal\views_bulk_operations_test\Plugin\Action;

use Drupal\Core\Messenger\MessengerTrait;
use Drupal\views_bulk_operations\Action\ViewsBulkOperationsActionBase;
use Drupal\views_bulk_operations\Action\ViewsBulkOperationsPreconfigurationInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\views\ViewExecutable;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Action for test purposes only.
 *
 * @Action(
 *   id = "views_bulk_operations_advanced_test_action",
 *   label = @Translation("VBO advanced test action"),
 *   type = "",
 *   confirm = TRUE,
 *   requirements = {
 *     "_permission" = "execute advanced test action",
 *   },
 * )
 */
class ViewsBulkOperationsAdvancedTestAction extends ViewsBulkOperationsActionBase implements ViewsBulkOperationsPreconfigurationInterface, PluginFormInterface {
  use MessengerTrait;

  /**
   * {@inheritdoc}
   */
  public function execute($entity = NULL) {
    // Check if $this->view is an instance of ViewsExecutable.
    if (!($this->view instanceof ViewExecutable)) {
      throw new \Exception('View passed to action object is not an instance of \Drupal\views\ViewExecutable.');
    }

    // Check if context array has been passed to the action.
    if (empty($this->context)) {
      throw new \Exception('Context array empty in action object.');
    }

    $this->messenger()->addMessage(sprintf('Test action (preconfig: %s, config: %s, label: %s)',
      $this->configuration['test_preconfig'],
      $this->configuration['test_config'],
      $entity->label()
    ));

    // Unpublish entity.
    if ($this->configuration['test_config'] === 'unpublish') {
      if (!$entity->isDefaultTranslation()) {
        $entity = \Drupal::service('entity_type.manager')->getStorage('node')->load($entity->id());
      }
      $entity->setUnpublished();
      $entity->save();
    }

    return $this->t('Test');
  }

  /**
   * {@inheritdoc}
   */
  public function buildPreConfigurationForm(array $element, array $values, FormStateInterface $form_state) {
    $element['test_preconfig'] = [
      '#title' => $this->t('Preliminary configuration'),
      '#type' => 'textfield',
      '#default_value' => isset($values['preconfig']) ? $values['preconfig'] : '',
    ];
    return $element;
  }

  /**
   * Configuration form builder.
   *
   * @param array $form
   *   Form array.
   * @param Drupal\Core\Form\FormStateInterface $form_state
   *   The form state object.
   *
   * @return array
   *   The configuration form.
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form['test_config'] = [
      '#title' => $this->t('Config'),
      '#type' => 'textfield',
      '#default_value' => $form_state->getValue('config'),
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function access($object, AccountInterface $account = NULL, $return_as_object = FALSE) {
    return $object->access('update', $account, $return_as_object);
  }

  /**
   * {@inheritdoc}
   */
  public static function finished($success, array $results, array $operations): ?RedirectResponse {
    // Let's return a bit different message. We don't except faliures
    // in tests as well so no need to check for a success.
    $operations = array_count_values($results['operations']);
    $details = [];
    foreach ($operations as $op => $count) {
      $details[] = $op . ' (' . $count . ')';
    }
    $message = static::translate('Custom processing message: @operations.', [
      '@operations' => implode(', ', $details),
    ]);
    static::message($message);
    return NULL;
  }

}
