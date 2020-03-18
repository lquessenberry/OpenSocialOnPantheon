<?php

namespace Drupal\data_policy\Plugin\views\filter;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\views\Plugin\views\filter\InOperator;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Simple filter to handle matching of multiple data policy revisions.
 *
 * @ingroup views_filter_handlers
 *
 * @ViewsFilter("user_consent_data_policy_revision")
 */
class UserConsentDataPolicyRevision extends InOperator {

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $connection;

  /**
   * The date formatter service.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a UserConsentDataPolicyRevision object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Database\Connection $connection
   *   The database connection.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $date_formatter
   *   The date formatter service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, Connection $connection, DateFormatterInterface $date_formatter, ConfigFactoryInterface $config_factory, EntityTypeManagerInterface $entity_type_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->connection = $connection;
    $this->dateFormatter = $date_formatter;
    $this->configFactory = $config_factory;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('database'),
      $container->get('date.formatter'),
      $container->get('config.factory'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getValueOptions() {
    if (isset($this->valueOptions)) {
      return $this->valueOptions;
    }

    $ids = $this->configFactory->getEditable('data_policy.data_policy')
      ->get('revision_ids');

    if (empty($ids)) {
      return $this->valueOptions = [];
    }

    $this->valueOptions = $this->connection->select('data_policy_revision', 'r')
      ->fields('r', ['vid', 'revision_created'])
      ->condition('vid', array_keys($ids), 'IN')
      ->orderBy('revision_created', 'DESC')
      ->execute()
      ->fetchAllKeyed();

    foreach ($this->valueOptions as &$timestamp) {
      $timestamp = $this->dateFormatter->format($timestamp);
    }

    return $this->valueOptions;
  }

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();

    $entity_id = $this->configFactory->getEditable('data_policy.data_policy')
      ->get('entity_id');

    if (!empty($entity_id)) {
      $revision_id = $this->entityTypeManager->getStorage('data_policy')
        ->load($entity_id)
        ->getRevisionId();

      $options['value']['default'] = [$revision_id];
    }

    return $options;
  }

}
