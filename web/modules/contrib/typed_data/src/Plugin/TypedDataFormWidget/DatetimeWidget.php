<?php

namespace Drupal\typed_data\Plugin\TypedDataFormWidget;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Datetime\Entity\DateFormat;
use Drupal\Core\Form\SubformStateInterface;
use Drupal\Core\TypedData\DataDefinition;
use Drupal\Core\TypedData\DataDefinitionInterface;
use Drupal\Core\TypedData\Type\DateTimeInterface;
use Drupal\Core\TypedData\TypedDataInterface;
use Drupal\typed_data\Form\SubformState;
use Drupal\typed_data\Widget\FormWidgetBase;
use Symfony\Component\Validator\ConstraintViolationListInterface;

/**
 * Plugin implementation of the 'datetime' widget.
 *
 * @TypedDataFormWidget(
 *   id = "datetime",
 *   label = @Translation("Datetime"),
 *   description = @Translation("A datetime input widget."),
 * )
 */
class DatetimeWidget extends FormWidgetBase {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return parent::defaultConfiguration() + [
      'label' => NULL,
      'description' => NULL,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function isApplicable(DataDefinitionInterface $definition) {
    return is_subclass_of($definition->getClass(), DateTimeInterface::class);
  }

  /**
   * {@inheritdoc}
   */
  public function form(TypedDataInterface $data, SubformStateInterface $form_state) {

    $now = \Drupal::time()->getRequestTime();
    $date_formatter = \Drupal::service('date.formatter');
    $params = [
      '%timezone' => $date_formatter->format($now, 'custom', 'T (e) \U\T\CP'),
      '%daylight_saving' => $date_formatter->format($now, 'custom', 'I') ? $this->t('currently in daylight saving mode') : $this->t('not in daylight saving mode'),
    ];
    $timezone_info = $this->t('Timezone : %timezone %daylight_saving.', $params);

    $form = SubformState::getNewSubForm();
    $form['value'] = [
      '#type' => 'datetime',
      '#title' => $this->configuration['label'] ?: $data->getDataDefinition()->getLabel(),
      '#description' => ($this->configuration['description'] ?: $data->getDataDefinition()->getDescription()) . '<br>' . $timezone_info,
      '#default_value' => $this->createDefaultDateTime($data->getValue()),
      '#required' => $data->getDataDefinition()->isRequired(),
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function extractFormValues(TypedDataInterface $data, SubformStateInterface $form_state) {
    $value = $form_state->getValue('value');
    if ($value instanceof DrupalDateTime) {
      $format = DateFormat::load('html_datetime')->getPattern();
      $value = $value->format($format);
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
    ];
  }

}
