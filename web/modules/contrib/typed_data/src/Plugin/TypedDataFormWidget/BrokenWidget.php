<?php

namespace Drupal\typed_data\Plugin\TypedDataFormWidget;

use Drupal\Core\Form\SubformStateInterface;
use Drupal\Core\TypedData\DataDefinition;
use Drupal\Core\TypedData\DataDefinitionInterface;
use Drupal\Core\TypedData\TypedDataInterface;
use Drupal\typed_data\Form\SubformState;
use Drupal\typed_data\Widget\FormWidgetBase;
use Symfony\Component\Validator\ConstraintViolationListInterface;

/**
 * Plugin implementation of the 'broken' widget.
 *
 * @TypedDataFormWidget(
 *   id = "broken",
 *   label = @Translation("Broken widget"),
 *   description = @Translation("A widget for everything that cannot be input or if a widget for a data type is not yet implemented."),
 * )
 */
class BrokenWidget extends FormWidgetBase {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return parent::defaultConfiguration() + ['label' => NULL];
  }

  /**
   * {@inheritdoc}
   */
  public function isApplicable(DataDefinitionInterface $definition) {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function form(TypedDataInterface $data, SubformStateInterface $form_state) {
    $form = SubformState::getNewSubForm();
    $form['value'] = [
      '#type' => 'item',
      '#title' => $this->configuration['label'] ?: $data->getDataDefinition()->getLabel(),
      '#markup' => $this->t('No widget exists for this data type.'),
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function extractFormValues(TypedDataInterface $data, SubformStateInterface $form_state) {
    $data->setValue(NULL);
  }

  /**
   * {@inheritdoc}
   */
  public function flagViolations(TypedDataInterface $data, ConstraintViolationListInterface $violations, SubformStateInterface $formState) {
    $formState->setErrorByName('value', $this->t('The field %field_label consists of the data type %data_type which cannot be input or a widget for this data type is not implemented yet.', [
      '%field_label' => $this->configuration['label'] ?: $data->getDataDefinition()->getLabel(),
      '%data_type' => $data->getDataDefinition()->getDataType(),
    ]));
  }

  /**
   * {@inheritdoc}
   */
  public function getConfigurationDefinitions(DataDefinitionInterface $definition) {
    return [
      'label' => DataDefinition::create('string')
        ->setLabel($this->t('Label')),
    ];
  }

}
