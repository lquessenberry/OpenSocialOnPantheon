<?php

namespace Drupal\data_policy_export\Plugin;

use Drupal\Component\Plugin\PluginBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\data_policy\Entity\DataPolicyInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base class for Data Policy export plugin plugins.
 */
abstract class DataPolicyExportPluginBase extends PluginBase implements DataPolicyExportPluginInterface, ContainerFactoryPluginInterface {

  use StringTranslationTrait;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  public $entityTypeManager;

  /**
   * The date formatter.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  public $dateFormatter;

  /**
   * The database.
   *
   * @var \Drupal\Core\Database\Connection
   */
  public $database;

  /**
   * UserExportPluginBase constructor.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $date_formatter
   *   The date formatter.
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, DateFormatterInterface $date_formatter, Connection $database) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->entityTypeManager = $entity_type_manager;
    $this->dateFormatter = $date_formatter;
    $this->database = $database;
  }

  /**
   * The create method.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   Container interface.
   * @param array $configuration
   *   An array of configuration.
   * @param string $plugin_id
   *   The plugin id.
   * @param mixed $plugin_definition
   *   The plugin definition.
   *
   * @return \Drupal\Core\Plugin\ContainerFactoryPluginInterface|\Drupal\data_policy_export\Plugin\DataPolicyExportPluginBase
   *   Returns the DataPolicyExportPluginBase.
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('date.formatter'),
      $container->get('database')
    );
  }

  /**
   * Returns the header.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup|string
   *   The header.
   */
  public function getHeader() {
    return '';
  }

  /**
   * Returns the value.
   *
   * @param \Drupal\data_policy\Entity\DataPolicyInterface $entity
   *   The Data Policy entity to get the value from.
   *
   * @return string
   *   The value.
   */
  public function getValue(DataPolicyInterface $entity) {
    return '';
  }

}
