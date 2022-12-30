<?php

namespace Drupal\typed_data\Widget;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\PluginBase;
use Drupal\Core\TypedData\TypedDataManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base class for 'form widget' plugin implementations.
 */
abstract class FormWidgetBase extends PluginBase implements FormWidgetInterface, ContainerFactoryPluginInterface {

  /**
   * The typed data plugin manager.
   *
   * @var \Drupal\Core\TypedData\TypedDataManagerInterface
   */
  protected $typedDataManager;

  /**
   * Constructs a FormWidgetBase object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\TypedData\TypedDataManagerInterface $typed_data_manager
   *   The typed data plugin manager.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, TypedDataManagerInterface $typed_data_manager) {
    parent::__construct($configuration + $this->defaultConfiguration(), $plugin_id, $plugin_definition);
    $this->typedDataManager = $typed_data_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('typed_data_manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getConfiguration() {
    return $this->configuration;
  }

  /**
   * {@inheritdoc}
   */
  public function setConfiguration(array $configuration) {
    $this->configuration = NestedArray::mergeDeep(
      $this->defaultConfiguration(),
      $configuration
    );
  }

  /**
   * Create a default DrupalDateTime object.
   *
   * This is used in the DateTimeWidget and DateTimeRangeWidget forms.
   *
   * @param string $date
   *   (optional) A formatted date string as stored by the widgets. If no value
   *   is given an empty date with time 12:00 noon is created.
   *
   * @return object
   *   A DrupalDateTime object with the required date and time values.
   */
  public function createDefaultDateTime($date) {
    if (!empty($date)) {
      $default = new DrupalDateTime($date);
    }
    else {
      // The DrupalDateTime object is created first with no parameters so that
      // it has the current users timezone. Then setDate with year 0 has the
      // effect that the widget date remains empty but allows a default time to
      // be set using setTime(). This is done in setDefaultDateTime().
      $default = new DrupalDateTime();
      $default->setDate(0, 1, 1);
      $default->setDefaultDateTime();
    }
    return $default;
  }

}
