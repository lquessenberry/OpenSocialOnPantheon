<?php

namespace Drupal\select2_form_test\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Test form to test the select2 element.
 *
 * @internal
 */
class Select2AjaxForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'form_test_select2_ajax';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $customize = FALSE) {
    $form['container'] = [
      '#type'       => 'container',
      '#attributes' => ['id' => 'my-container'],
    ];

    $form['select2_ajax'] = [
      '#type' => 'select2',
      '#title' => 'Ajax',
      '#options' => [],
      '#target_type' => 'entity_test_mulrevpub',
      '#selection_handler' => 'default:entity_test_mulrevpub',
      '#selection_settings' => [
        'target_bundles' => ['entity_test_mulrevpub' => 'entity_test_mulrevpub'],
        'auto_create' => TRUE,
        'auto_create_bundle' => 'entity_test_mulrevpub',
      ],
      '#autocreate' => [
        'bundle' => 'entity_test_mulrevpub',
        'uid' => '1',
      ],
      '#multiple' => TRUE,
    ];

    $form['call_ajax'] = [
      '#type'   => 'submit',
      '#value'  => $this->t('Call ajax'),
      '#submit' => ['::callAjax'],
      '#ajax'   => [
        'callback' => '::ajaxCallback',
        'wrapper'  => 'my-container',
      ],
    ];

    $form['submit'] = ['#type' => 'submit', '#value' => 'Submit'];

    return $form;
  }

  /**
   * Dummy ajax call.
   */
  public function callAjax() {}

  /**
   * Dummy ajax callback.
   */
  public function ajaxCallback($form, $form_state) {
    return $form['container'];
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $form_state->setResponse(new JsonResponse($form_state->getValues()));
  }

}
