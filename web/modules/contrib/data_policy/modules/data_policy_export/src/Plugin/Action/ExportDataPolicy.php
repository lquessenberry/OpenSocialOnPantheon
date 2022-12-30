<?php

namespace Drupal\data_policy_export\Plugin\Action;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Messenger\MessengerTrait;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\data_policy\Entity\UserConsentInterface;
use Drupal\views_bulk_operations\Action\ViewsBulkOperationsActionBase;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\Core\Form\FormStateInterface;
use Psr\Log\LoggerInterface;
use Drupal\Core\Url;
use Drupal\csv_serialization\Encoder\CsvEncoder;
use League\Csv\Writer;
use Drupal\Core\Link;
use Drupal\data_policy_export\Plugin\DataPolicyExportPluginManager;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Exports data policies to CSV.
 *
 * @Action(
 *   id = "data_policy_export_data_policy_action",
 *   label = @Translation("Export the selected data policies to CSV"),
 *   type = "user_consent",
 *   confirm = FALSE
 * )
 */
class ExportDataPolicy extends ViewsBulkOperationsActionBase implements ContainerFactoryPluginInterface, PluginFormInterface {
  use MessengerTrait;
  use StringTranslationTrait;

  /**
   * The Data Policy export plugin manager.
   *
   * @var \Drupal\data_policy_export\Plugin\DataPolicyExportPluginManager
   */
  protected $dataPolicyExportPlugin;

  /**
   * A logger instance.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * The current user account.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * The date formatter service.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * The file system service.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * Constructs a ExportDataPolicy object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\data_policy_export\Plugin\DataPolicyExportPluginManager $dataPolicyExportPlugin
   *   The user export plugin manager.
   * @param \Psr\Log\LoggerInterface $logger
   *   A logger instance.
   * @param \Drupal\Core\Session\AccountProxyInterface $currentUser
   *   The current user account.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   Config factory for the export plugin access.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $date_formatter
   *   Date formatter to be able to format the date to human-friendly.
   * @param \Drupal\Core\File\FileSystemInterface $file_system
   *   The file system service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, DataPolicyExportPluginManager $dataPolicyExportPlugin, LoggerInterface $logger, AccountProxyInterface $currentUser, ConfigFactoryInterface $configFactory, DateFormatterInterface $date_formatter, FileSystemInterface $file_system) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->dataPolicyExportPlugin = $dataPolicyExportPlugin;
    $this->logger = $logger;
    $this->currentUser = $currentUser;
    $this->dateFormatter = $date_formatter;
    $this->fileSystem = $file_system;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static($configuration, $plugin_id, $plugin_definition,
      $container->get('plugin.manager.data_policy_export_plugin'),
      $container->get('logger.factory')->get('action'),
      $container->get('current_user'),
      $container->get('config.factory'),
      $container->get('date.formatter'),
      $container->get('file_system')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function executeMultiple(array $entities) {

    // Check if headers exists.
    if (empty($this->context['sandbox']['results']['headers'])) {
      $this->context['sandbox']['results']['headers'] = [
        'Name',
        'State',
        'Datetime',
      ];
    }

    // Create the file if applicable.
    if (empty($this->context['sandbox']['results']['file_path'])) {
      // Store only the name relative to the output directory. On platforms such
      // as Pantheon, different batch ticks can happen on different webheads.
      // This can cause the file mount path to change, thus changing where on
      // disk the tmp folder is actually located.
      $this->context['sandbox']['results']['file_path'] = $this->generateFilePath();
      $file_path = $this->getBaseOutputDirectory() . DIRECTORY_SEPARATOR . $this->context['sandbox']['results']['file_path'];

      $csv = Writer::createFromPath($file_path, 'w');
      $csv->setDelimiter(',');
      $csv->setEnclosure('"');
      $csv->setEscape('\\');

      $csv->insertOne($this->context['sandbox']['results']['headers']);
    }
    else {
      $file_path = $this->getBaseOutputDirectory() . DIRECTORY_SEPARATOR . $this->context['sandbox']['results']['file_path'];
      $csv = Writer::createFromPath($file_path, 'a');
    }

    // Add formatter.
    $csv->addFormatter([new CsvEncoder(), 'formatRow']);

    // Now add the entities to export.
    foreach ($entities as $entity_id => $entity) {
      $row = [];
      $owner = $entity->getOwner();
      $ownerName = '';
      if ($owner) {
        $ownerName = $owner->getDisplayName();
      }
      $row[] = $ownerName;
      $row[] = $this->getStateName($entity->state->value);
      $row[] = $this->dateFormatter->format($entity->getChangedTime(), 'short');
      $csv->insertOne($row);
    }

    if (($this->context['sandbox']['current_batch'] * $this->context['sandbox']['batch_size']) >= $this->context['sandbox']['total']) {
      $data = @file_get_contents($file_path);
      $name = basename($this->context['sandbox']['results']['file_path']);
      $path = 'private://csv';

      if ($this->fileSystem->prepareDirectory($path, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS) && (file_save_data($data, $path . '/' . $name))) {
        $url = Url::fromUri(file_create_url($path . '/' . $name));
        $link = Link::fromTextAndUrl($this->t('Download file'), $url);

        $this->messenger()->addMessage($this->t('Export is complete. @link', [
          '@link' => $link->toString(),
        ]));
      }
      else {
        $this->messenger()->addMessage($this->t('Could not save the export file.'), 'error');
        $this->logger->error('Could not save the export file on: %name.', ['%name' => $name]);
      }
    }
  }

  /**
   * Helper function to return the human-friendly name to the CSV export.
   *
   * @param int $state_id
   *   The ID of the state for which we want to get the text.
   *
   * @return mixed
   *   The text we will be using in the export.
   */
  public function getStateName($state_id) {
    $options = [
      UserConsentInterface::STATE_UNDECIDED => $this->t('Undecided'),
      UserConsentInterface::STATE_NOT_AGREE => $this->t('Not agree'),
      UserConsentInterface::STATE_AGREE => $this->t('Agree'),
    ];

    return $options[$state_id];
  }

  /**
   * {@inheritdoc}
   */
  public function execute($object = NULL) {
    $this->executeMultiple([$object]);
  }

  /**
   * {@inheritdoc}
   */
  public function access($object, AccountInterface $account = NULL, $return_as_object = FALSE) {
    /** @var \Drupal\data_policy\Entity\DataPolicyInterface $object */
    // @todo Check for export access instead.
    return $object->access('view', $account, $return_as_object);
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    return $form;
  }

  /**
   * Returns the directory that forms the base for this exports file output.
   *
   * This method wraps FileSystemInterface::getTempDirectory() to give
   * inheriting classes the ability to use a different file system than the
   * temporary file system.
   * This was previously possible but was changed in #3075818.
   *
   * @return string
   *   The path to the Drupal directory that should be used for this export.
   */
  protected function getBaseOutputDirectory() : string {
    return $this->fileSystem->getTempDirectory();
  }

  /**
   * Returns a unique file path for this export.
   *
   * The returned path is relative to getBaseOutputDirectory(). This allows it
   * to work on distributed systems where the temporary file path may change
   * in between batch ticks.
   *
   * To make sure the file can be downloaded, the path must be declared in the
   * download pattern of the social user export module.
   *
   * @see data_policy_export_file_download()
   *
   * @return string
   *   The path to the file.
   */
  protected function generateFilePath() : string {
    $hash = md5(microtime(TRUE));
    return 'export-data-policies-' . substr($hash, 20, 12) . '.csv';
  }

}
