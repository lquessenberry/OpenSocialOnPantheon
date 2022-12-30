<?php

namespace Drupal\update_helper\Generators;

use Drupal\Core\Extension\Extension;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\update_helper\ConfigHandler;
use Drupal\update_helper\Events\CommandExecuteEvent;
use Drupal\update_helper\Events\CommandInteractEvent;
use Drupal\update_helper\Events\UpdateHelperEvents;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use DrupalCodeGenerator\Command\DrupalGenerator;
use DrupalCodeGenerator\Asset\AssetCollection;
use Symfony\Component\Console\Question\ChoiceQuestion;

/**
 * Implements update_helper:configuration-update command.
 */
class ConfigurationUpdate extends DrupalGenerator {

  /**
   * {@inheritdoc}
   */
  protected string $name = 'update_helper:configuration-update';

  /**
   * {@inheritdoc}
   */
  protected string $description = 'Generates a configuration update';

  /**
   * {@inheritdoc}
   */
  protected string $alias = 'config-update';

  /**
   * The module extension list service.
   *
   * @var \Drupal\Core\Extension\ModuleExtensionList
   */
  protected $extensionList;

  /**
   * Drupal\update_helper\ConfigHandler definition.
   *
   * @var \Drupal\update_helper\ConfigHandler
   */
  protected $configHandler;

  /**
   * Drupal\Core\Extension\ModuleHandler definition.
   *
   * @var \Drupal\Core\Extension\ModuleHandler
   */
  protected $moduleHandler;

  /**
   * Event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected $eventDispatcher;

  /**
   * {@inheritdoc}
   */
  public function __construct(ModuleExtensionList $extension_list, EventDispatcherInterface $event_dispatcher, ModuleHandlerInterface $module_handler, ConfigHandler $config_handler) {
    parent::__construct($this->name);

    $this->extensionList = $extension_list;
    $this->eventDispatcher = $event_dispatcher;
    $this->configHandler = $config_handler;
    $this->moduleHandler = $module_handler;
  }

  /**
   * {@inheritdoc}
   */
  protected function generate(array &$vars): void {
    $extensions = $this->getExtensions();
    $question = new Question('Enter a module/profile');
    $question->setAutocompleterValues(array_keys($extensions));
    $question->setValidator(function ($module_name) use ($extensions) {
      if (empty($module_name) || !array_key_exists($module_name, $extensions)) {
        throw new \InvalidArgumentException(
          sprintf(
            'The module name "%s" is not valid',
            $module_name
          )
        );
      }
      return $module_name;
    });

    $vars['module'] = $this->io->askQuestion($question);

    $question = new ChoiceQuestion('Do you want to create a post_update or hook_update_N update function?',
      ['post_update', 'hook_update_N'], 'post_update');
    $update_method = $this->io->askQuestion($question);

    if ($update_method === 'post_update') {
      $question = new Question('Please enter the machine name for the update', NULL);
      $question->setValidator([static::class, 'validateMachineName']);

      // Number post update hooks for implicit ordering of post update functions
      // created by the Update Helper module. This is because Update Helper uses
      // diffs and therefore requires that it's updates are run in a particular
      // order. The update numbers DO NOT reflect the module schema and start
      // from 0001.
      /** @var \Drupal\Core\Update\UpdateRegistry $service */
      $service = \Drupal::service('update.post_update_registry');
      $updates = array_merge($service->getModuleUpdateFunctions($vars['module']), array_keys($service->getRemovedPostUpdates($vars['module'])));
      $lastUpdate = 0;
      foreach($updates as $update) {
        if (preg_match('/^'. preg_quote($vars['module']) . '_post_update_(\d*)_.*$/', $update, $matches)) {
          $lastUpdate = max($lastUpdate, $matches[1]);
        }
      }
      $lastUpdate = str_pad((string) $lastUpdate + 1, 4, '0', STR_PAD_LEFT);
      $vars['update_name'] = 'post_update_' . $lastUpdate . '_' . $this->io->askQuestion($question);
    }
    else {
      /** @var \Drupal\Core\Update\UpdateHookRegistry $service */
      $service = \Drupal::service('update.update_hook_registry');
      $lastUpdate = $service->getInstalledVersion($vars['module']);
      $nextUpdate = $lastUpdate > 0 ? ($lastUpdate + 1) : 8001;

      $question = new Question('Please provide the number for update hook to be added', $nextUpdate);
      $question->setValidator(function ($update_number) use ($lastUpdate) {
        if ($update_number === NULL || $update_number === '' || !is_numeric($update_number) || $update_number <= $lastUpdate) {
          throw new \InvalidArgumentException(
            sprintf(
              'The update number "%s" is not valid',
              $update_number
            )
          );
        }
        return $update_number;
      });
      $vars['update_name'] = 'update_' . $this->io->askQuestion($question);
    }

    $vars['description'] = $this->ask('Please enter a description text for update. This will be used as the comment for update hook.', 'Configuration update.', '::validateRequired');

    $enabled_modules = array_filter($this->moduleHandler->getModuleList(), function (Extension $extension) {
      return ($extension->getType() === 'module' || $extension->getType() === 'profile');
    });
    $enabled_modules = array_keys($enabled_modules);

    $question = new ChoiceQuestion('Provide a comma-separated list of modules which configurations should be included in update.', $enabled_modules);
    $question->setMultiselect(TRUE);
    $vars['include-modules'] = $this->io->askQuestion($question);

    $vars['from-active'] = $this->confirm('Generate update from active configuration in database to configuration in Yml files?');

    // Get additional options provided by other modules.
    $event = new CommandInteractEvent($vars);
    $this->eventDispatcher->dispatch($event, UpdateHelperEvents::COMMAND_GCU_INTERACT);

    foreach ($event->getQuestions() as $key => $question) {
      $vars[$key] = $this->io->askQuestion($question);
    }

    // Get patch data and save it into file.
    $patch_data = $this->configHandler->generatePatchFile($vars['include-modules'], $vars['from-active']);

    if (!empty($patch_data)) {

      // Get additional options provided by other modules.
      $event = new CommandExecuteEvent($vars);
      $this->eventDispatcher->dispatch($event, UpdateHelperEvents::COMMAND_GCU_EXECUTE);

      foreach ($event->getTemplatePaths() as $path) {
        $this->getHelper('renderer')->prependPath($path);
      }

      $this->assets = new AssetCollection($event->getAssets());

      $patch_file_path = $this->configHandler->getPatchFile($vars['module'], static::getUpdateFunctionName($vars['module'], $vars['update_name']), TRUE);

      // Add the patchfile.
      $this->addFile($patch_file_path)
        ->content($patch_data);
    }
    else {
      $this->io->write('There are no configuration changes that should be exported for the update.', TRUE);
    }
  }

  /**
   * Get installed non_core extensions.
   *
   * @return \Drupal\Core\Extension\Extension[]
   *   The list of installed non-core extensions keyed by the extension name.
   */
  protected function getExtensions(): array {
    $extensions = array_filter($this->extensionList->getList(),
      static function ($extension): bool {
        return ($extension->origin !== 'core');
      });

    ksort($extensions);
    return $extensions;
  }

  /**
   * Get update hook function name.
   *
   * @param string $module_name
   *   Module name.
   * @param string $update_name
   *   Update number.
   *
   * @return string
   *   Returns update hook function name.
   */
  public static function getUpdateFunctionName($module_name, $update_name): string {
    return $module_name . '_' . $update_name;
  }

}
