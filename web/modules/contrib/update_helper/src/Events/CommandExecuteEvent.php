<?php

namespace Drupal\update_helper\Events;

use Drupal\Component\EventDispatcher\Event;
use DrupalCodeGenerator\Asset\Asset;

/**
 * Event for command execute.
 *
 * @package Drupal\update_helper\Events
 */
class CommandExecuteEvent extends Event {

  /**
   * The collected variables.
   *
   * @var array
   */
  protected $vars;

  /**
   * Assets that should be generated.
   *
   * @var array
   */
  protected $assets = [];

  /**
   * Paths to look for template files.
   *
   * @var array
   */
  protected $templatePaths = [];

  /**
   * Command execute event constructor.
   *
   * @param array $vars
   *   The collected vars.
   */
  public function __construct(array $vars) {
    $this->vars = $vars;
  }

  /**
   * Get the collected vars.
   *
   * @return array
   *   All the collected vars.
   */
  public function getVars() {
    return $this->vars;
  }

  /**
   * Get the assets that should be generated.
   *
   * @return \DrupalCodeGenerator\Asset\Asset[]
   *   Assets that should be generated.
   */
  public function getAssets() {
    return $this->assets;
  }

  /**
   * Add an asset.
   *
   * @param \DrupalCodeGenerator\Asset\Asset $asset
   *   The asset to add to the array.
   *
   * @return $this
   */
  public function addAsset(Asset $asset) {
    $this->assets[] = $asset;
    return $this;
  }

  /**
   * Add a template path.
   *
   * @param string $template_path
   *   The path for templates.
   *
   * @return $this
   */
  public function addTemplatePath($template_path) {
    $this->templatePaths[] = $template_path;
    return $this;
  }

  /**
   * Get all template paths.
   *
   * @return array
   *   An array of paths.
   */
  public function getTemplatePaths() {
    return $this->templatePaths;
  }

}
