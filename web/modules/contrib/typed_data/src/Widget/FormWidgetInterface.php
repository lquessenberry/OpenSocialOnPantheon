<?php

namespace Drupal\typed_data\Widget;

use Drupal\Component\Plugin\ConfigurableInterface;
use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Core\Form\SubformStateInterface;
use Drupal\Core\TypedData\DataDefinitionInterface;
use Drupal\Core\TypedData\TypedDataInterface;
use Symfony\Component\Validator\ConstraintViolationListInterface;

/**
 * Interface definition for form widget plugins.
 */
interface FormWidgetInterface extends ConfigurableInterface, PluginInspectionInterface {

  /**
   * Returns if the widget can be used for the provided data.
   *
   * @param \Drupal\Core\TypedData\DataDefinitionInterface $definition
   *   The definition of the edited data.
   *
   * @return bool
   *   Whether the data can be edited with the widget.
   */
  public function isApplicable(DataDefinitionInterface $definition);

  /**
   * Creates the widget's form elements for editing the given data.
   *
   * Note that the FAPI element callbacks (such as #process, #element_validate,
   * #value_callback, etc.) used by the widget do not have access to the
   * definition passed to this method. Therefore, if any information is needed
   * from that definition by those callbacks, the widget implementing this
   * method must extract the needed properties from the data definition and set
   * them as ad-hoc $element['#custom'] properties, for later use by its element
   * callbacks.
   *
   * @param \Drupal\Core\TypedData\TypedDataInterface $data
   *   The data to be edited.
   * @param \Drupal\Core\Form\SubformStateInterface $form_state
   *   The form state of the widget's form.
   *
   * @return array[]
   *   The form elements for the given data. Note that this must be an array,
   *   holding one or more form-elements.
   */
  public function form(TypedDataInterface $data, SubformStateInterface $form_state);

  /**
   * Extracts the data value from submitted form values.
   *
   * @param \Drupal\Core\TypedData\TypedDataInterface $data
   *   The data to be updated with the submitted form values.
   * @param \Drupal\Core\Form\SubformStateInterface $form_state
   *   The form state of the widget's form.
   */
  public function extractFormValues(TypedDataInterface $data, SubformStateInterface $form_state);

  /**
   * Reports validation errors against actual form elements.
   *
   * Depending on the widget's internal structure, a property-level validation
   * error needs to be flagged on the right sub-element.
   *
   * Note that validation is run according to the validation constraints of
   * the data definition. In addition to that, widget-level validation may be
   * provided using the regular #element_validate callbacks of the form API.
   *
   * @param \Drupal\Core\TypedData\TypedDataInterface $data
   *   The data to be edited.
   * @param \Symfony\Component\Validator\ConstraintViolationListInterface $violations
   *   A list of constraint violations to flag.
   * @param \Drupal\Core\Form\SubformStateInterface $formState
   *   The form state of the widget's form.
   */
  public function flagViolations(TypedDataInterface $data, ConstraintViolationListInterface $violations, SubformStateInterface $formState);

  /**
   * Defines the supported configuration settings.
   *
   * If the widget is configurable, this method must define the supported
   * setting values. The definitions may include suiting widgets and widget
   * configurations for generating a configuration form.
   *
   * @param \Drupal\Core\TypedData\DataDefinitionInterface $definition
   *   The definition of the edited data.
   *
   * @return \Drupal\typed_data\Context\ContextDefinitionInterface[]
   *   An array of context definitions describing the configuration values,
   *   keyed by configuration setting name. The keys must match the actual keys
   *   of the supported configuration.
   */
  public function getConfigurationDefinitions(DataDefinitionInterface $definition);

}
