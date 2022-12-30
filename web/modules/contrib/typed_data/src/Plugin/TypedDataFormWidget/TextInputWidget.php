<?php

namespace Drupal\typed_data\Plugin\TypedDataFormWidget;

use Drupal\Core\Form\SubformStateInterface;
use Drupal\Core\TypedData\DataDefinition;
use Drupal\Core\TypedData\DataDefinitionInterface;
use Drupal\Core\TypedData\Type\FloatInterface;
use Drupal\Core\TypedData\Type\IntegerInterface;
use Drupal\Core\TypedData\Type\StringInterface;
use Drupal\Core\TypedData\TypedDataInterface;
use Drupal\typed_data\Form\SubformState;
use Drupal\typed_data\Widget\FormWidgetBase;
use Symfony\Component\Validator\ConstraintViolationListInterface;

/**
 * Plugin implementation of the 'text_input' widget.
 *
 * @TypedDataFormWidget(
 *   id = "text_input",
 *   label = @Translation("Text input"),
 *   description = @Translation("A simple, one-line text input widget."),
 * )
 */
class TextInputWidget extends FormWidgetBase {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return parent::defaultConfiguration() + [
      'label' => NULL,
      'description' => NULL,
      'placeholder' => NULL,
      'size' => 60,
      'maxlength' => 255,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function isApplicable(DataDefinitionInterface $definition) {
    return is_subclass_of($definition->getClass(), StringInterface::class) ||
      is_subclass_of($definition->getClass(), IntegerInterface::class) ||
      is_subclass_of($definition->getClass(), FloatInterface::class);
  }

  /**
   * {@inheritdoc}
   */
  public function form(TypedDataInterface $data, SubformStateInterface $form_state) {
    $form = SubformState::getNewSubForm();
    $form['value'] = [
      '#type' => 'textfield',
      '#title' => $this->configuration['label'] ?: $data->getDataDefinition()->getLabel(),
      '#description' => $this->configuration['description'] ?: $data->getDataDefinition()->getDescription(),
      '#default_value' => $data->getValue(),
      '#placeholder' => $this->configuration['placeholder'],
      '#size' => $this->configuration['size'],
      '#maxlength' => $this->configuration['maxlength'],
      '#required' => $data->getDataDefinition()->isRequired(),
      '#disabled' => $data->getDataDefinition()->isReadOnly(),
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function extractFormValues(TypedDataInterface $data, SubformStateInterface $form_state) {
    // Ensure empty values correctly end up as NULL value.
    $value = $form_state->getValue('value');
    if ($value === '') {
      $value = NULL;
    }
    $data->setValue($value);
  }

  /**
   * {@inheritdoc}
   */
  public function flagViolations(TypedDataInterface $data, ConstraintViolationListInterface $violations, SubformStateInterface $formState) {
    foreach ($violations as $violation) {
      /** @var ConstraintViolationInterface $violation */
      $formState->setErrorByName('value', $violation->getMessage());
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getConfigurationDefinitions(DataDefinitionInterface $definition) {
    return [
      'label' => DataDefinition::create('string')
        ->setLabel($this->t('Label')),
      'description' => DataDefinition::create('string')
        ->setLabel($this->t('Description')),
      'placeholder' => DataDefinition::create('string')
        ->setLabel($this->t('Placeholder value')),
      'size' => DataDefinition::create('integer')
        ->setLabel($this->t('Input field size')),
      'maxlength' => DataDefinition::create('integer')
        ->setLabel($this->t('Maximum text length')),
    ];
  }

}
