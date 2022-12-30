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
class Select2OptgroupForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'form_test_select2_optgroup';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $customize = FALSE) {

    $form['select2_optgroups'] = [
      '#type' => 'select2',
      '#title' => 'Optgroups',
      '#options' => [
        0 => 'Foo',
        1 => 'Bar',
        'Baba' => [
          3 => 'Nana',
        ],
        2 => 'Gaga',
      ],
    ];

    $form['submit'] = ['#type' => 'submit', '#value' => 'Submit'];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $form_state->setResponse(new JsonResponse($form_state->getValues()));
  }

}
