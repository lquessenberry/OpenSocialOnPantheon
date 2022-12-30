<?php

namespace Drupal\update_helper\Events;

use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\update_helper\Generators\ConfigurationUpdate;
use DrupalCodeGenerator\Asset\File;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Subscriber for "generate:configuration:update" command.
 */
class CommandSubscriber implements EventSubscriberInterface {

  /**
   * The module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * Command subscriber class.
   *
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler service.
   */
  public function __construct(ModuleHandlerInterface $module_handler) {
    $this->moduleHandler = $module_handler;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      UpdateHelperEvents::COMMAND_GCU_EXECUTE => [
        ['onExecute', 10],
      ],
    ];
  }

  /**
   * Handles execute for configuration update generation to create update hook.
   *
   * @param \Drupal\update_helper\Events\CommandExecuteEvent $execute_event
   *   Command execute event.
   */
  public function onExecute(CommandExecuteEvent $execute_event) {
    $vars = $execute_event->getVars();

    $module_path = $this->moduleHandler->getModule($vars['module'])
      ->getPath();
    if (strpos($vars['update_name'], 'post_update') === 0) {
      $update_file = $module_path . '/' . $vars['module'] . '.post_update.php';
    }
    else {
      $update_file = $module_path . '/' . $vars['module'] . '.install';
    }

    $vars['update_hook_name'] = ConfigurationUpdate::getUpdateFunctionName($vars['module'], $vars['update_name']);
    $vars['file_exists'] = file_exists($update_file);

    // Add the update hook template.
    $asset = (new File($update_file))
      ->vars($vars)
      ->appendIfExists()
      ->template('configuration_update_hook.php.twig');

    $execute_event->addAsset($asset);

    $execute_event->addTemplatePath(__DIR__ . '/../../templates');

  }

}
