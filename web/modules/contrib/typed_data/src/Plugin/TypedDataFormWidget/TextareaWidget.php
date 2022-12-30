<?php

namespace Drupal\typed_data\Plugin\TypedDataFormWidget;

use Drupal\Core\Form\SubformStateInterface;
use Drupal\Core\TypedData\DataDefinition;
use Drupal\Core\TypedData\DataDefinitionInterface;
use Drupal\Core\TypedData\Plugin\DataType\Email;
use Drupal\Core\TypedData\Type\DateTimeInterface;
use Drupal\Core\TypedData\Type\DurationInterface;
use Drupal\Core\TypedData\Type\StringInterface;
use Drupal\Core\TypedData\Type\UriInterface;
use Drupal\Core\TypedData\TypedDataInterface;
use Drupal\typed_data\Form\SubformState;
use Drupal\typed_data\Widget\FormWidgetBase;
use Symfony\Component\Validator\ConstraintViolationListInterface;

/**
 * Plugin implementation of the 'textarea' widget.
 *
 * @TypedDataFormWidget(
 *   id = "textarea",
 *   label = @Translation("Textarea"),
 *   description = @Translation("A multi-line text input widget."),
 * )
 */
class TextareaWidget extends FormWidgetBase {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return parent::defaultConfiguration() + [
      'label' => NULL,
      'description' => NULL,
      'placeholder' => NULL,
      'rows' => 5,
      'cols' => 60,
      'resizable' => 'both',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function isApplicable(DataDefinitionInterface $definition) {
    if (is_subclass_of($definition->getClass(), StringInterface::class)) {
      $result = TRUE;
      // Never use textarea for editing dates, durations, e-mail or URIs.
      $classes = [
        DateTimeInterface::class,
        DurationInterface::class,
        Email::class,
        UriInterface::class,
      ];
      foreach ($classes as $class) {
        $result = $result && !is_subclass_of($definition->getClass(), $class) && $definition->getClass() != $class;
      }
      return $result;
    }
    else {
      return FALSE;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function form(TypedDataInterface $data, SubformStateInterface $form_state) {
    $form = SubformState::getNewSubForm();
    $form['value'] = [
      '#type' => 'textarea',
      '#title' => $this->configuration['label'] ?: $data->getDataDefinition()->getLabel(),
      '#description' => $this->configuration['description'] ?: $data->getDataDefinition()->getDescription(),
      '#default_value' => $data->getValue(),
      '#placeholder' => $this->configuration['placeholder'],
      '#rows' => $this->configuration['rows'],
      '#cols' => $this->configuration['cols'],
      '#resizable' => $this->configuration['resizable'],
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
      'rows' => DataDefinition::create('integer')
        ->setLabel($this->t('Number of rows in the text box')),
      'cols' => DataDefinition::create('integer')
        ->setLabel($this->t('Number of columns in the text box')),
      'resizable' => DataDefinition::create('string')
        ->setLabel($this->t('Controls whether the text area is resizable'))
        ->setDescription($this->t('Allowed values are "none", "vertical", "horizontal", or "both".')),
    ];
  }

}
