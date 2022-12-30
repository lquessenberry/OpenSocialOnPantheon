<?php

namespace Drupal\ajax_comments;

use Drupal\ajax_comments\Utility;
use Drupal\Core\Form\FormStateInterface;
use Drupal\user\PrivateTempStore;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * A service to help store and retrieve data to be used across HTTP requests.
 *
 * @package Drupal\ajax_comments
 */
class TempStore {

  /**
   * The PrivateTempStore service.
   *
   * This service stores temporary data to be used across HTTP requests.
   *
   * @var \Drupal\user\PrivateTempStore
   */
  protected $privateTempStore;

  /**
   * TempStore constructor.
   *
   * @param \Drupal\Core\TempStore\PrivateTempStoreFactory $private_temp_store_factory
   *   The factory to create the PrivateTempStore object.
   */
  public function __construct(PrivateTempStoreFactory $private_temp_store_factory) {
    $this->privateTempStore = $private_temp_store_factory->get('ajax_comments');
  }

  /**
   * Store a newly-added comment ID for retrieval across HTTP requests.
   *
   * @param mixed $cid
   *   The comment ID to store for the newly-added comment.
   */
  public function setCid($cid) {
    $this->privateTempStore->set('cid', $cid);
  }

  /**
   * Retrieve a newly-added comment ID set in a previous request by setCid().
   *
   * @return mixed
   *   The comment ID saved in ::setCid().
   */
  public function getCid() {
    return $this->privateTempStore->get('cid');
  }

  public function setViewMode($entity_type, $viewmode) {
    $this->privateTempStore->set('view_mode_entity_type_' . $entity_type, $viewmode);
  }

  public function getViewMode($entity_type) {
    return $this->privateTempStore->get('view_mode_entity_type_' . $entity_type);
  }

  /**
   * Get a single selector value, without the '#' prefix.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   * @param string $selector
   *   The selector to retrieve.
   *
   * @return string
   *   The value of the selector.
   */
  public function getSelectorValue(Request $request, $selector) {
    $selectors = $this->getSelectors($request);
    $value = $selectors[$selector];
    return substr($value, strpos($value, '#') + 1);
  }

  /**
   * Retrieve the selectors included in the form submission HTTP request.
   *
   * Store the selectors in the privateTempStore so that they are available
   * for a subsequent HTTP response (when the #lazy_builder callback runs).
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   * @param bool $overwrite
   *   Boolean to indicate if an existing selector should be overwritten if
   *   a different value exists in the request.
   *
   * @return array
   *   An array of the selectors, keyed by selector name.
   */
  public function getSelectors(Request $request, $overwrite = FALSE) {
    $selectors = [
      'wrapper_html_id' => NULL,
      'form_html_id' => NULL,
    ];
    foreach ($selectors as $selector_name => $selector) {
      $request_value = $request->request->get($selector_name);
      $existing_value = $this->privateTempStore->get($selector_name);

      if (!empty($request_value) && ($overwrite || empty($existing_value))) {
        $value = '#' . $request_value;
        $this->privateTempStore->set($selector_name, $value);
        $selectors[$selector_name] = $value;
      }
      else {
        $selectors[$selector_name] = $existing_value;
      }
    }
    return $selectors;
  }

  /**
   * Set the selector for an ajax element for use across HTTP requests.
   *
   * @param string $selector
   *   The selector to update.
   * @param string $value
   *   The new value for the selector.
   *
   * @throws \Drupal\user\TempStoreException
   */
  public function setSelector($selector, $value) {
    $this->privateTempStore->set($selector, '#' . $value);
  }

  /**
   * Update the temp store values while rebuilding a form, when necessary.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   * @param array $form
   *   A form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state object.
   * @param bool $is_validating
   *   Indicates if this method has been called from a form validation function.
   *
   * @throws \Drupal\user\TempStoreException
   */
  public function processForm(Request $request, $form, FormStateInterface $form_state, $is_validating = FALSE) {
    $form_machine_name = $this->privateTempStore->get('form_machine_name');
    $form_build_info = $form_state->getBuildInfo();

    // If this form is being built through ajax, update the stored value
    // of form_html_id.
    if (Utility::isAjaxRequest($request)) {
      if ($is_validating) {
        $this->setSelector('form_html_id', $form['form_html_id']['#value']);
        $this->privateTempStore->set('form_machine_name', $form_build_info['form_id']);
      }
      elseif ($form_build_info['form_id'] === $form_machine_name) {
        $this->setSelector('form_html_id', $form['form_html_id']['#value']);
      }
    }
  }

  /**
   * Delete the values from the privateTempStore.
   *
   * @throws \Drupal\user\TempStoreException
   */
  public function deleteAll() {
    $values = [
      'wrapper_html_id',
      'form_html_id',
      'form_machine_name',
      'cid',
    ];
    foreach ($values as $value) {
      $this->privateTempStore->delete($value);
    }
  }

}
