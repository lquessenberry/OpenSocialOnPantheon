<?php

namespace Drupal\private_message\Controller;

/**
 * Interface for the Private Message module's AjaxController.
 */
interface AjaxControllerInterface {

  /**
   * Create AJAX responses for JavaScript requests.
   *
   * @param string $op
   *   The type of data to build for the response.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The ajax response
   */
  public function ajaxCallback($op);

  /**
   * Create AJAX response containing usernames for an autocomplete callback.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The ajax response
   */
  public function privateMessageMembersAutocomplete();

}
