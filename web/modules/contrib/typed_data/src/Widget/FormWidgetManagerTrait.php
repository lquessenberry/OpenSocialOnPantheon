<?php

namespace Drupal\typed_data\Widget;

/**
 * Helper to access the form widget manager service.
 */
trait FormWidgetManagerTrait {

  /**
   * The widget manager.
   *
   * @var \Drupal\typed_data\Widget\FormWidgetManagerInterface
   */
  protected $widgetManager;

  /**
   * Sets the widget manager.
   *
   * @param \Drupal\typed_data\Widget\FormWidgetManagerInterface $widgetManager
   *   The widget manager.
   *
   * @return $this
   */
  public function setFormWidgetManager(FormWidgetManagerInterface $widgetManager) {
    $this->widgetManager = $widgetManager;
    return $this;
  }

  /**
   * Gets the widget manager.
   *
   * @return \Drupal\typed_data\Widget\FormWidgetManagerInterface
   *   The widget manager.
   */
  public function getFormWidgetManager() {
    if (empty($this->widgetManager)) {
      $this->widgetManager = \Drupal::service('plugin.manager.typed_data_form_widget');
    }

    return $this->widgetManager;
  }

}
