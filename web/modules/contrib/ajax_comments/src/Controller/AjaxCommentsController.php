<?php

namespace Drupal\ajax_comments\Controller;

use Drupal\ajax_comments\TempStore;
use Drupal\ajax_comments\Utility;
use Drupal\comment\CommentInterface;
use Drupal\comment\Controller\CommentController;
use Drupal\Core\Ajax\AfterCommand;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\AppendCommand;
use Drupal\Core\Ajax\BeforeCommand;
use Drupal\Core\Ajax\CloseModalDialogCommand;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Ajax\PrependCommand;
use Drupal\Core\Ajax\RemoveCommand;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\RouterInterface;

/**
 * Controller routines for AJAX comments routes.
 */
class AjaxCommentsController extends ControllerBase {

  /**
   * Class prefix to apply to each comment.
   *
   * @var string
   *   A prefix used to build class name applied to each comment.
   */
  public static $commentClassPrefix = 'js-ajax-comments-id-';

  /**
   * Service to turn render arrays into HTML strings.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * The Router service.
   *
   * A router class for Drupal.
   *
   * @var \Symfony\Component\Routing\RouterInterface
   */
  protected $router;

  /**
   * The TempStore service.
   *
   * This service stores temporary data to be used across HTTP requests.
   *
   * @var \Drupal\ajax_comments\TempStore
   */
  protected $tempStore;

  /**
   * The messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * Constructs a AjaxCommentsController object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The render service.
   * @param \Symfony\Component\Routing\RouterInterface $router
   *   The Router service.
   * @param \Drupal\ajax_comments\TempStore $temp_store
   *   The TempStore service.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The Messenger service.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, AccountInterface $current_user, RendererInterface $renderer, RouterInterface $router, TempStore $temp_store, MessengerInterface $messenger) {
    $this->entityTypeManager = $entity_type_manager;
    $this->currentUser = $current_user;
    $this->renderer = $renderer;
    $this->router = $router;
    $this->tempStore = $temp_store;
    $this->messenger = $messenger;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('current_user'),
      $container->get('renderer'),
      $container->get('router.no_access_checks'),
      $container->get('ajax_comments.temp_store'),
      $container->get('messenger')
    );
  }

  /**
   * Get the prefix for a selector class for an individual comment.
   *
   * @return string
   *   The portion of a CSS class name that prepends the comment ID.
   */
  public static function getCommentSelectorPrefix() {
    return '.' . static::$commentClassPrefix;
  }

  /**
   * Build a comment field render array for the ajax response.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity that has the comment field.
   * @param string $field_name
   *   The machine name of the comment field.
   *
   * @return array
   *   A render array for the updated comment field.
   */
  protected function renderCommentField(EntityInterface $entity, $field_name) {
    $comment_field = $entity->get($field_name);
    // Load the display settings to ensure that the field formatter
    // configuration is properly applied to the rendered field when it is
    // returned in the ajax response.

    /** @var \Drupal\ajax_comments\TempStore $tempStore */
    $tempStore = \Drupal::service('ajax_comments.temp_store');
    $view_mode = $tempStore->getViewMode($entity->getEntityType()->getLabel()->getUntranslatedString());
    $display_options = $this->entityTypeManager
      ->getStorage('entity_view_display')
      ->load($entity->getEntityTypeId() . '.' . $entity->bundle() . '.' . $view_mode)
      ->getComponent($field_name);
    $comment_display = $comment_field->view($display_options);

    // Add default classes to comments elements.
    Utility::addCommentClasses($comment_display[0]['comments']);

    // To avoid infinite nesting of #theme_wrappers elements on subsequent
    // ajax responses, unset them here.
    unset($comment_display['#theme_wrappers']);

    // Remove unneeded route parameters.
    unset($comment_display[0]['comments']['pager']['#route_parameters']['entity_type']);
    unset($comment_display[0]['comments']['pager']['#route_parameters']['entity']);
    unset($comment_display[0]['comments']['pager']['#route_parameters']['field_name']);
    unset($comment_display[0]['comments']['pager']['#route_parameters']['pid']);

    /**

	    $entity_type = $entity->getEntityType();

	    // For replies, the passed $entity is the parent comment.
	    // However, for the pager we want the parent entity.
	    if ($entity_type->id() === 'comment') {
	      $entity = $entity->getCommentedEntity();
	      $entity_type = $entity->getEntityType();
	    }

	    $handler = $this->entityTypeManager()->getRouteProviders($entity_type->id())['html'];
	    $route_collection = $handler->getRoutes($entity_type);
	    $name = 'entity.' . $entity_type->get('id') . '.canonical';
	    $route = $route_collection->get($name);
	    // Override the ajax route object with the actual entity route.
	    $entity_url = $entity->toURL();
	    if ($route) {
	      $comment_display[0]['comments']['pager']['#route_name'] = $route;
	      $comment_display[0]['comments']['pager']['#route_parameters'] = $entity_url->getRouteParameters();
	    }
    */

    return $comment_display;
  }

  /**
   * Create an ajax response to replace the comment field.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request object.
   * @param \Drupal\Core\Ajax\AjaxResponse $response
   *   The response object being built.
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity that has the comment field.
   * @param string $field_name
   *   The machine name of the comment field.
   * @param int|null $pid
   *   The entity id of the parent comment, if applicable, NULL otherwise.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The modified ajax response.
   */
  protected function buildCommentFieldResponse(Request $request, AjaxResponse $response, EntityInterface $entity, $field_name, $pid = NULL) {
    // Build a comment field render array for the ajax response.
    $comment_display = $this->renderCommentField($entity, $field_name);

    // Get the wrapper HTML id selector.
    $selectors = $this->tempStore->getSelectors($request);
    $wrapper_html_id = $selectors['wrapper_html_id'];

    // Rendering the comment form below (as part of comment_display) triggers
    // form processing.
    $response->addCommand(new ReplaceCommand($wrapper_html_id, $comment_display));

    // Store the new wrapper_html_id, in case it is needed for other commands.
    $this->tempStore->setSelector('wrapper_html_id', $comment_display['#attributes']['id']);

    return $response;
  }

  /**
   * Add messages to the ajax response.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request object.
   * @param \Drupal\Core\Ajax\AjaxResponse $response
   *   The response object being built.
   * @param string $selector
   *   The DOM selector used to insert status messages.
   * @param string $position
   *   Indicates whether to use PrependCommand, BeforeCommand, AppendCommand,
   *   or AfterCommand.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The modified ajax response.
   */
  protected function addMessages(Request $request, AjaxResponse $response, $selector = '', $position = 'prepend') {
    $settings = \Drupal::config('ajax_comments.settings');
    $notify = $settings->get('notify');

    if ($notify || !empty($this->messenger->messagesByType(MessengerInterface::TYPE_ERROR))) {
      if (empty($selector)) {
        // Use the first id found in the ajax replacement markup to be
        // inserted into the page as the selector, if none was provided.
        foreach ($response->getCommands() as $command) {
          if ($command['command'] === 'insert' && $command['method'] === 'replaceWith') {
            $markup = $command['data']->__toString();
            if (preg_match('/\sid="(.*)"/', $markup, $matches)) {
              $selector = '#' . $matches[1];
              break;
            }
          }
        }
      }
      // Add any status messages.
      $status_messages = ['#type' => 'status_messages'];

      switch ($position) {
        case 'replace':
          $command = new ReplaceCommand(
            $selector,
            $this->renderer->renderRoot($status_messages)
          );
          break;

        case 'after':
          $command = new AfterCommand(
            $selector,
            $this->renderer->renderRoot($status_messages)
          );
          break;

        case 'before':
          $command = new BeforeCommand(
            $selector,
            $this->renderer->renderRoot($status_messages)
          );
          break;

        case 'append':
          $command = new AppendCommand(
            $selector,
            $this->renderer->renderRoot($status_messages)
          );
          break;

        case 'prepend':
        default:
          $command = new PrependCommand(
            $selector,
            $this->renderer->renderRoot($status_messages)
          );
      }
      $response->addCommand(
        $command
      );
    }
    else {
      // Render messages to avoid display them when reloading the page.
      $status_messages = ['#type' => 'status_messages'];
      $this->renderer->renderRoot($status_messages);
    }

    return $response;
  }

  /**
   * Returns the comment edit form.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request object.
   * @param \Drupal\comment\CommentInterface $comment
   *   The comment entity.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse|\Symfony\Component\HttpFoundation\RedirectResponse
   *   The Ajax response, or a redirect response if not using ajax.
   */
  public function edit(Request $request, CommentInterface $comment) {
    $is_ajax = Utility::isAjaxRequest($request);

    if ($is_ajax) {
      $response = new AjaxResponse();

      // Get the selectors.
      $selectors = $this->tempStore->getSelectors($request, $overwrite = TRUE);
      $wrapper_html_id = $selectors['wrapper_html_id'];

      // Hide anchor.
      $response->addCommand(new InvokeCommand('a#comment-' . $comment->id(), 'hide'));

      // Hide comment.
      $response->addCommand(new InvokeCommand(static::getCommentSelectorPrefix() . $comment->id(), 'hide'));

      // Remove any existing status messages in the comment field,
      // if applicable.
      $response->addCommand(new RemoveCommand($wrapper_html_id . ' .js-ajax-comments-messages'));

      // Insert the comment form.
      $form = $this->entityFormBuilder()->getForm($comment);
      $response->addCommand(new AfterCommand(static::getCommentSelectorPrefix() . $comment->id(), $form));

      // TODO: Get this custom ajax command working later.
      // if (\Drupal::config('ajax_comments.settings')->get('enable_scroll')) {
      //   $response->addCommand(new ajaxCommentsScrollToElementCommand('.ajax-comments-reply-form-' . $comment->getCommentedEntityId() . '-' . $comment->get('pid')->target_id . '-' . $comment->id()));
      // }

      // Don't delete the tempStore variables here; we need them
      // to persist for the save() method below, where the form returned
      // here will be submitted.
      // Instead, return the response without calling $this->tempStore->deleteAll().
      return $response;
    }
    else {
      // If the user attempts to access the edit link directly (e.g., at
      // /ajax_comments/1/edit), redirect to the core comment edit form.
      $redirect = Url::fromRoute(
        'entity.comment.edit_form',
        ['comment' => $comment->id()]
      )
        ->setAbsolute()
        ->toString();
      $response = new RedirectResponse($redirect);
      return $response;
    }

  }

  /**
   * Submit handler for the comment reply and edit forms.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request object.
   * @param \Drupal\comment\CommentInterface $comment
   *   The comment entity.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The Ajax response.
   */
  public function save(Request $request, CommentInterface $comment) {
    $response = new AjaxResponse();

    // Store the selectors from the incoming request, if applicable.
    // If the selectors are not in the request, the stored ones will
    // not be overwritten.
    $this->tempStore->getSelectors($request, $overwrite = TRUE);

    // Rebuild the form to trigger form submission.
    $form = $this->entityFormBuilder()->getForm($comment, 'default', ['editing' => TRUE]);

    // Check for errors.
    if (empty($this->messenger->messagesByType('error'))) {
      $errors = FALSE;
      // If there are no errors, set the ajax-updated
      // selector value for the form.
      $this->tempStore->setSelector('form_html_id', $form['#attributes']['id']);

      // Build the updated comment field and insert into a replaceWith
      // response. Also prepend any status messages in the response.
      $response = $this->buildCommentFieldResponse(
        $request,
        $response,
        $comment->getCommentedEntity(),
        $comment->get('field_name')->value
      );
    }
    else {
      $errors = TRUE;
      // Retrieve the selector values for use in building the response.
      $selectors = $this->tempStore->getSelectors($request, $overwrite = TRUE);
      $wrapper_html_id = $selectors['wrapper_html_id'];
      $form_html_id = $selectors['form_html_id'];

      // If there are errors, remove old messages and reload the form.
      $response->addCommand(new RemoveCommand($wrapper_html_id . ' .js-ajax-comments-messages'));
      $response->addCommand(new ReplaceCommand($form_html_id, $form));
    }
    // If this is a new comment being added from this method, it is being
    // inserted as a reply to another comment, and there is no $comment->id()
    // yet. Insert the message after the parent comment instead.
    if ($comment->isNew()) {
      // Retrieve the comment id of the new comment, which was saved in
      // AjaxCommentsForm::save() during the previous HTTP request.
      $cid = $this->tempStore->getCid();

      // Try to insert the message above the new comment.
      if (!empty($cid) && !$errors && \Drupal::currentUser()->hasPermission('skip comment approval')) {
        $selector = static::getCommentSelectorPrefix() . $cid;
        $response = $this->addMessages(
          $request,
          $response,
          $selector,
          'before'
        );
      }
      // If the new comment is not to be shown immediately, or if there are
      // errors, insert the message directly below the parent comment.
      else {
        $response = $this->addMessages(
          $request,
          $response,
          static::getCommentSelectorPrefix() . $comment->get('pid')->target_id,
          'after'
        );
      }
    }
    // Otherwise, if this is an edit to an existing comment, insert the
    // messages above the existing comment item.
    else {
      $response = $this->addMessages(
        $request,
        $response,
        static::getCommentSelectorPrefix() . $comment->id(),
        'before'
      );
    }

    // Clear out the tempStore variables.
    $this->tempStore->deleteAll();

    // Remove the libraries from the response, otherwise when
    // core/misc/drupal.js is reinserted into the DOM, the following line of
    // code will execute, causing Drupal.attachBehaviors() to run on the entire
    // document, and reattach behaviors to DOM elements that already have them:
    // @code
    // // Attach all behaviors.
    // domready(function () { Drupal.attachBehaviors(document, drupalSettings); });
    // @endcode
    $attachments = $response->getAttachments();
    // Need to have only 'core/drupalSettings' in the asset library list.
    // If neither 'core/drupalSettings', nor a library with a dependency on it,
    // is in the list of libraries, drupalSettings will be stripped out of the
    // ajax response by \Drupal\Core\Asset\AssetResolver::getJsAssets().
    $attachments['library'] = ['core/drupalSettings'];
    // We need to keep the drupalSettings in the response, otherwise the
    // #ajax properties in the form definition won't be properly attached to
    // the rebuilt comment field returned in the ajax response, and subsequent
    // ajax interactions will be broken.
    $response->setAttachments($attachments);

    return $response;
  }

  /**
   * Cancel handler for the comment edit form.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request object.
   * @param int $cid
   *   The id of the comment being edited, or 0 if this is a new comment.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The Ajax response.
   */
  public function cancel(Request $request, $cid) {
    $response = new AjaxResponse();

    // Get the selectors.
    $selectors = $this->tempStore->getSelectors($request, $overwrite = TRUE);
    $wrapper_html_id = $selectors['wrapper_html_id'];
    $form_html_id = $selectors['form_html_id'];

    if ($cid != 0) {
      // Show the hidden anchor.
      $response->addCommand(new InvokeCommand('a#comment-' . $cid, 'show', [200, 'linear']));

      // Show the hidden comment.
      $response->addCommand(new InvokeCommand(static::getCommentSelectorPrefix() . $cid, 'show', [200, 'linear']));
    }

    // Remove the form.
    $response->addCommand(new RemoveCommand($form_html_id));

    // Remove any messages, if applicable.
    $response->addCommand(new RemoveCommand($wrapper_html_id . ' .js-ajax-comments-messages'));

    // Clear out the tempStore variables.
    $this->tempStore->deleteAll();

    return $response;
  }

  /**
   * Builds ajax response for deleting a comment.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request object.
   * @param \Drupal\comment\CommentInterface $comment
   *   The comment entity.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The Ajax response.
   */
  public function delete(Request $request, CommentInterface $comment) {
    $response = new AjaxResponse();

    // Store the selectors from the incoming request, if applicable.
    // If the selectors are not in the request, the stored ones will
    // not be overwritten.
    $this->tempStore->getSelectors($request, $overwrite = TRUE);

    $response->addCommand(new CloseModalDialogCommand());

    // Rebuild the form to trigger form submission.
    $this->entityFormBuilder()->getForm($comment, 'delete');

    // Build the updated comment field and insert into a replaceWith response.
    // Also prepend any status messages in the response.
    $response = $this->buildCommentFieldResponse(
      $request,
      $response,
      $comment->getCommentedEntity(),
      $comment->get('field_name')->value
    );

    // Calling $this->buildCommentFieldResponse() updates the stored selectors.
    $selectors = $this->tempStore->getSelectors($request);
    $wrapper_html_id = $selectors['wrapper_html_id'];

    $response = $this->addMessages(
      $request,
      $response,
      $wrapper_html_id
    );

    // Clear out the tempStore variables.
    $this->tempStore->deleteAll();

    return $response;
  }

  /**
   * Builds ajax response for adding a new comment without a parent comment.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request object.
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity this comment belongs to.
   * @param string $field_name
   *   The field_name to which the comment belongs.
   * @param int $pid
   *   (optional) Some comments are replies to other comments. In those cases,
   *   $pid is the parent comment's comment ID. Defaults to NULL.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The Ajax response.
   *
   * @see \Drupal\comment\Controller\CommentController::getReplyForm()
   */
  public function add(Request $request, EntityInterface $entity, $field_name, $pid = NULL) {
    $response = new AjaxResponse();

    // Store the selectors from the incoming request, if applicable.
    // If the selectors are not in the request, the stored ones will
    // not be overwritten.
    $this->tempStore->getSelectors($request, $overwrite = TRUE);

    // Check the user's access to reply.
    // The user should not have made it this far without proper permission,
    // but adding this access check as a fallback.
    $this->replyAccess($request, $response, $entity, $field_name, $pid);

    // If $this->replyAccess() added any commands to the AjaxResponse,
    // it means that access was denied, so we should NOT submit the form
    // and rebuild the comment field. Just return the response with the
    // error message.
    if (!empty($response->getCommands())) {
      return $response;
    }

    // Build the comment entity form.
    // This approach is very similar to the one taken in
    // \Drupal\comment\CommentLazyBuilders::renderForm().
    $comment = $this->entityTypeManager()->getStorage('comment')->create([
      'entity_id' => $entity->id(),
      'pid' => $pid,
      'entity_type' => $entity->getEntityTypeId(),
      'field_name' => $field_name,
    ]);

    // Rebuild the form to trigger form submission.
    $form = $this->entityFormBuilder()->getForm($comment);

    // Check for errors.
    if (empty($this->messenger->messagesByType('error'))) {
      // If there are no errors, set the ajax-updated
      // selector value for the form.
      $this->tempStore->setSelector('form_html_id', $form['#attributes']['id']);

      // Build the updated comment field and insert into a replaceWith
      // response.
      $response = $this->buildCommentFieldResponse(
        $request,
        $response,
        $entity,
        $field_name
      );
    }
    else {
      // Retrieve the selector values for use in building the response.
      $selectors = $this->tempStore->getSelectors($request, $overwrite = TRUE);
      $wrapper_html_id = $selectors['wrapper_html_id'];

      // If there are errors, remove old messages.
      $response->addCommand(new RemoveCommand($wrapper_html_id . ' .js-ajax-comments-messages'));
    }

    // The form_html_id should have been updated by the form constructor when
    // $this->buildCommentFieldResponse() was called, so retrieve the updated
    // selector values for use in building the response.
    $selectors = $this->tempStore->getSelectors($request);
    $form_html_id = $selectors['form_html_id'];

    // Prepend any status messages in the response.
    $response = $this->addMessages(
      $request,
      $response,
      $form_html_id,
      'before'
    );

    // Clear out the tempStore variables.
    $this->tempStore->deleteAll();

    // Remove the libraries from the response, otherwise when
    // core/misc/drupal.js is reinserted into the DOM, the following line of
    // code will execute, causing Drupal.attachBehaviors() to run on the entire
    // document, and reattach behaviors to DOM elements that already have them:
    // @code
    // // Attach all behaviors.
    // domready(function () { Drupal.attachBehaviors(document, drupalSettings); });
    // @endcode
    $attachments = $response->getAttachments();
    // Need to have only 'core/drupalSettings' in the asset library list.
    // If neither 'core/drupalSettings', nor a library with a dependency on it,
    // is in the list of libraries, drupalSettings will be stripped out of the
    // ajax response by \Drupal\Core\Asset\AssetResolver::getJsAssets().
    $attachments['library'] = ['core/drupalSettings'];
    // We need to keep the drupalSettings in the response, otherwise the
    // #ajax properties in the form definition won't be properly attached to
    // the rebuilt comment field returned in the ajax response, and subsequent
    // ajax interactions will be broken.
    $response->setAttachments($attachments);

    return $response;
  }

  /**
   * Builds ajax response to display a form to reply to another comment.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request object.
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity this comment belongs to.
   * @param string $field_name
   *   The field_name to which the comment belongs.
   * @param int $pid
   *   The parent comment's comment ID.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse|\Symfony\Component\HttpFoundation\RedirectResponse
   *   The Ajax response, or a redirect response if not using ajax.
   *
   * @see \Drupal\comment\Controller\CommentController::getReplyForm()
   */
  public function reply(Request $request, EntityInterface $entity, $field_name, $pid) {
    $is_ajax = Utility::isAjaxRequest($request);

    if ($is_ajax) {
      $response = new AjaxResponse();

      // Get the selectors.
      $selectors = $this->tempStore->getSelectors($request, $overwrite = TRUE);
      $wrapper_html_id = $selectors['wrapper_html_id'];

      // Check the user's access to reply.
      // The user should not have made it this far without proper permission,
      // but adding this access check as a fallback.
      $this->replyAccess($request, $response, $entity, $field_name, $pid);

      // If $this->replyAccess() added any commands to the AjaxResponse,
      // it means that access was denied, so we should NOT ajax load the
      // reply form. Instead, return the response with the error messages
      // immediately.
      if (!empty($response->getCommands())) {
        return $response;
      }

      // Remove any existing status messages and ajax reply forms in the
      // comment field, if applicable.
      $response->addCommand(new RemoveCommand($wrapper_html_id . ' .js-ajax-comments-messages'));
      $response->addCommand(new RemoveCommand($wrapper_html_id . ' .ajax-comments-form-reply'));

      // Build the comment entity form.
      // This approach is very similar to the one taken in
      // \Drupal\comment\CommentLazyBuilders::renderForm().
      $comment = $this->entityTypeManager()->getStorage('comment')->create([
        'entity_id' => $entity->id(),
        'pid' => $pid,
        'entity_type' => $entity->getEntityTypeId(),
        'field_name' => $field_name,
      ]);
      // Build the comment form.
      $form = $this->entityFormBuilder()->getForm($comment);
      $response->addCommand(new AfterCommand(static::getCommentSelectorPrefix() . $pid, $form));

      // Don't delete the tempStore variables here; we need them
      // to persist for the saveReply() method, where the form returned
      // here will be submitted.
      // Instead, return the response without calling $this->tempStore->deleteAll().
      return $response;
    }
    else {
      // If the user attempts to access the comment reply form with JavaScript
      // disabled, degrade gracefully by redirecting to the core comment
      // reply form.
      $redirect = Url::fromRoute(
        'comment.reply',
        [
          'entity_type' => $entity->getEntityTypeId(),
          'entity' => $entity->id(),
          'field_name' => $field_name,
          'pid' => $pid,
        ]
      )
        ->setAbsolute()
        ->toString();
      $response = new RedirectResponse($redirect);
      return $response;
    }
  }

  /**
   * Builds ajax response to save a submitted reply to another comment.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request object.
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity this comment belongs to.
   * @param string $field_name
   *   The field_name to which the comment belongs.
   * @param int $pid
   *   The parent comment's comment ID.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The Ajax response.
   */
  public function saveReply(Request $request, EntityInterface $entity, $field_name, $pid) {
    $response = new AjaxResponse();

    // Check the user's access to reply.
    // The user should not have made it this far without proper permission,
    // but adding this access check as a fallback.
    $this->replyAccess($request, $response, $entity, $field_name, $pid);

    // If $this->replyAccess() added any commands to the AjaxResponse,
    // it means that access was denied, so we should NOT submit the form
    // and rebuild the comment field. Instead, return the response
    // immediately and abort the save.
    if (!empty($response->getCommands())) {
      return $response;
    }

    // Build a dummy comment entity to pass to $this->save(), which will use
    // it to rebuild the comment entity form to trigger form submission.
    // @code
    // $form = $this->entityFormBuilder()->getForm($comment, 'default', ['editing' => TRUE]);
    // @endcode
    // Note that this approach will correctly process the form submission
    // even though we are passing in an empty, dummy comment, because two steps
    // later in the call stack, \Drupal\Core\Form\FormBuilder::buildForm() is
    // called, and it checks the current request object for form submission
    // values if there aren't any in the form state, yet:
    // @code
    // $input = $form_state->getUserInput();
    // if (!isset($input)) {
    //   $input = $form_state->isMethodType('get') ? $request->query->all() : $request->request->all();
    //   $form_state->setUserInput($input);
    // }
    // @endcode
    // This approach is very similar to the one taken in
    // \Drupal\comment\CommentLazyBuilders::renderForm().
    $comment = $this->entityTypeManager()->getStorage('comment')->create([
      'entity_id' => $entity->id(),
      'pid' => $pid,
      'entity_type' => $entity->getEntityTypeId(),
      'field_name' => $field_name,
    ]);
    // Rebuild the form to trigger form submission.
    return $this->save($request, $comment);
  }

  /**
   * Check the user's permission to post a comment.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request object.
   * @param \Drupal\Core\Ajax\AjaxResponse $response
   *   The response object being built.
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity this comment belongs to.
   * @param string $field_name
   *   The field_name to which the comment belongs.
   * @param int $pid
   *   (optional) Some comments are replies to other comments. In those cases,
   *   $pid is the parent comment's comment ID. Defaults to NULL.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse $response
   *   The ajax response, if access is denied.
   */
  public function replyAccess(Request $request, AjaxResponse $response, EntityInterface $entity, $field_name, $pid = NULL) {

    // Get the selectors.
    $selectors = $this->tempStore->getSelectors($request);
    $wrapper_html_id = $selectors['wrapper_html_id'];
    $form_html_id = $selectors['form_html_id'];

    $access = CommentController::create(\Drupal::getContainer())
      ->replyFormAccess($entity, $field_name, $pid);

    // If access is not explicitly allowed, then we forbid it.
    if (!$access->isAllowed()) {
      $selector = $form_html_id;
      if (empty($selector)) {
        $selector = $wrapper_html_id;
      }
      $this->messenger->addError(t('You do not have permission to post a comment.'));
      // If this is a new top-level comment (not a reply to another comment so
      // no $pid), replace the comment form with the error message.
      if (empty($pid)) {
        // Remove any existing status messages in the comment field,
        // if applicable.
        $response->addCommand(new RemoveCommand($wrapper_html_id . ' .js-ajax-comments-messages'));
        // Add the error message.
        $response = $this->addMessages($request, $response, $selector, 'replace');
      }
      // Otherwise, if this is a reply, reload the field without reply links
      // or a reply form, and insert the error message at the top.
      else {
        $response = $this->buildCommentFieldResponse($request, $response, $entity, $field_name, $pid);
        // The wrapper_html_id should have been updated when
        // $this->buildCommentFieldResponse() was called, so retrieve
        // the updated selector values for use in building the response.
        $selectors = $this->tempStore->getSelectors($request);
        $selector = $selectors['wrapper_html_id'];
        $response = $this->addMessages($request, $response, $selector, 'prepend');
      }

      // Clear out the tempStore variables.
      $this->tempStore->deleteAll();

      return $response;
    }
  }

}
