<?php

namespace Drupal\ajax_comments;

use Drupal\ajax_comments\Controller\AjaxCommentsController;
use Drupal\Component\Utility\Html;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\EventSubscriber\AjaxResponseSubscriber;
use Drupal\Core\EventSubscriber\MainContentViewSubscriber;
use Drupal\Core\Render\Element;
use Drupal\Core\Render\Markup;
use Symfony\Component\HttpFoundation\Request;

/**
 * Provides various helper methods for Ajax Comments.
 *
 * @package Drupal\ajax_comments
 */
class Utility {

  /**
   * An array of generated render arrays for the current request.
   *
   * This array is keyed by entity_type.bundle.view_mode.id.
   *
   * @var array
   */
  protected static $entityRenderArrays;

  /**
   * Store a generated entity render array.
   *
   * @param array $build
   *   The render array to store.
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity with the render array to store.
   * @param string $view_mode
   *   The view mode of the render array.
   */
  public static function setEntityRenderArray($build, ContentEntityInterface $entity, $view_mode = 'default') {
    $prefix = static::isAjaxRequest(\Drupal::request()) ? 'ajax' : 'standard';
    $entity_type = $entity->getEntityTypeId();
    $bundle = $entity->bundle();
    $id = $entity->id();
    $key = $entity_type . '.' . $bundle . '.' . $view_mode . '.' . $id;
    static::$entityRenderArrays[$key] = $build;
  }

  /**
   * Retrieve a stored entity render array.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity whose render array should be loaded.
   * @param string $view_mode
   *   The view mode of the render array to load.
   *
   * @return array
   *   The stored render array, or an empty array if the stored render array
   *   cannot be found for the entity/view mode combination.
   */
  public static function getEntityRenderArray(ContentEntityInterface $entity, $view_mode = 'default') {
    $prefix = static::isAjaxRequest(\Drupal::request()) ? 'ajax' : 'standard';
    $entity_type = $entity->getEntityTypeId();
    $bundle = $entity->bundle();
    $id = $entity->id();
    $modes = [
      $view_mode,
      '_custom',
      'default',
    ];
    // First try to retrieve the render array from the static variable.
    // This generally works on requests made through ajax.
    foreach ($modes as $mode) {
      $key = $entity_type . '.' . $bundle . '.' . $mode . '.' . $id;
      if (isset(static::$entityRenderArrays[$key])) {
        return static::$entityRenderArrays[$key];
      }
    }
    return [];
  }

  /**
   * Given an entity and the name of a comment field, return the wrapper id.
   *
   * Using an entity with a comment field, and the machine name of the comment
   * field, return the id attribute value of the wrapper element around the
   * comment field, for use in ajax responses.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $commented_entity
   *   The entity that has the comment field being updated.
   * @param string $field_name
   *   The machine name of the comment field.
   *
   * @return string
   *   The value of the id attribute of the comment field wrapper element.
   */
  public static function getWrapperIdFromEntity(ContentEntityInterface $commented_entity, $field_name) {

    /** @var \Drupal\ajax_comments\TempStore $tempStore */
    $tempStore = \Drupal::service('ajax_comments.temp_store');
    $view_mode = $tempStore->getViewMode($commented_entity->getEntityType()->getLabel()->getUntranslatedString());

    // Load the early-stage render array for the commented entity.
    $build = \Drupal::entityTypeManager()
      ->getViewBuilder($commented_entity->getEntityTypeId())
      ->view($commented_entity, $view_mode);

    // First, attempt to retrieve the cached markup for the commented entity
    // and use a regular expression to get the id attribute value of the
    // wrapper element. This approach is necessary because Drupal will first
    // attempt to load the rendered field markup from cache and ignore any
    // render arrays generated during this HTTP response, so if markup is
    // returned from cache, the wrapper id will need to match the id attribute
    // in the returned markup.
    //
    // The following code block is adapted from
    // \Drupal\Core\Render\Renderer::doRender(). The code for loading the
    // markup from cache is not structured in a way that it can be called
    // independently, and attempting to call Renderer::doRender() directly
    // from this context results in an infinite loop, so the code needs to be
    // duplicated here.
    //
    // Try to fetch the prerendered element from cache,
    // replace any placeholders and return the final markup.
    if (isset($build['#cache']['keys'])) {
      $required_cache_contexts = \Drupal::getContainer()
        ->getParameter('renderer.config')['required_cache_contexts'];

      if (isset($build['#cache']['contexts'])) {
        $build['#cache']['contexts'] = Cache::mergeContexts($build['#cache']['contexts'], $required_cache_contexts);
      }
      else {
        $build['#cache']['contexts'] = $required_cache_contexts;
      }
      $cached_element = \Drupal::service('render_cache')->get($build);
      if ($cached_element !== FALSE) {
        $build = $cached_element;
        // Mark the element markup as safe if is it a string.
        if (is_string($build['#markup'])) {
          $build['#markup'] = Markup::create($build['#markup']);
        }
      }
    }

    $matches = [];
    // If the cache returned markup, attempt to find the wrapper element id
    // attribute using a regular expression.
    if (isset($build['#markup'])) {
      // Generate the known, unchanging portion of the wrapper element id.
      $wrapper_html_id_prefix = Html::getId(
        $commented_entity->getEntityTypeId() . '-' . $commented_entity->bundle() . '-' . $field_name
      );
      // Use regex to get the full wrapper id, using the known part of the id.
      preg_match('/\sid="(' . $wrapper_html_id_prefix . '[^"]*)"/', $build['#markup']->__toString(), $matches);
    }
    if (!empty($matches[1])) {
      $wrapper_html_id = $matches[1];
    }
    else {
      // If the field markup cannot be retrieved from cache, attempt to
      // retrieve the render array from the static variable on this class
      // or from the cache set by this class (both approaches are tried
      // in the method static::getEntityRenderArray()).
      $render_array = static::getEntityRenderArray($commented_entity, $view_mode);
      if (isset($render_array[$field_name])) {
        $wrapper_html_id = $render_array[$field_name]['#attributes']['id'];
      }
      else {
        // If the render array cannot be retrieved from the static variable or
        // the cache, generate it now and get the wrapper id from it.
        $render_array = $commented_entity->get($field_name)->view();
        $wrapper_html_id = $render_array['#attributes']['id'];
      }
    }
    // Make sure users can alter the wrapper if necessary.
    \Drupal::moduleHandler()->alter('ajax_comments_wrapper_id', $wrapper_html_id, $commented_entity, $field_name);

    return $wrapper_html_id;
  }

  /**
   * Check if a request was made through ajax.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request object.
   * @param array $input
   *   (optional) The form input returned from $form_state->getUserInput().
   *
   * @return bool
   *   Whether or not the request was made using ajax.
   */
  public static function isAjaxRequest(Request $request, $input = []) {
    $has_ajax_parameter = $request
      ->request
      ->has(AjaxResponseSubscriber::AJAX_REQUEST_PARAMETER);
    $has_ajax_input_parameter = !empty(
      $input[AjaxResponseSubscriber::AJAX_REQUEST_PARAMETER]
    );
    $has_ajax_format = $request
        ->query
        ->get(MainContentViewSubscriber::WRAPPER_FORMAT) == 'drupal_ajax';
    return $has_ajax_parameter || $has_ajax_input_parameter || $has_ajax_format;
  }

  /**
   * Check if the request is for a modal dialog.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request object.
   *
   * @return bool
   *   Whether or not the request was made using ajax.
   */
  public static function isModalRequest(Request $request) {
    return $request
      ->query
      ->get(MainContentViewSubscriber::WRAPPER_FORMAT) === 'drupal_modal';
  }

  /**
   * Helper function to add wrapper classes to comments render arrays.
   *
   * @param array $elements
   *   The comment field render array.
   */
  public static function addCommentClasses(array &$elements) {
    foreach (Element::children($elements) as $key) {
      if (!isset($elements[$key]['#comment'])) {
        continue;
      }
      $elements[$key]['#attributes']['class'][] = AjaxCommentsController::$commentClassPrefix . $elements[$key]['#comment']->id();
    }
  }

}
