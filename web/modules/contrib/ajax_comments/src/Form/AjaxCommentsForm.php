<?php

namespace Drupal\ajax_comments\Form;

use Drupal\ajax_comments\FieldSettingsHelper;
use Drupal\ajax_comments\TempStore;
use Drupal\ajax_comments\Utility;
use Drupal\comment\CommentForm;
use Drupal\Component\Utility\Html;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\CurrentRouteMatch;
use Drupal\Core\Routing\RouteMatch;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Provides ajax enhancements to core default Comment form.
 *
 * @package Drupal\ajax_comments
 */
class AjaxCommentsForm extends CommentForm {

  /**
   * The CurrentRouteMatch service.
   *
   * @var \Drupal\Core\Routing\CurrentRouteMatch
   */
  protected $currentRouteMatch;

  /**
   * The FieldSettingsHelper service.
   *
   * @var \Drupal\ajax_comments\FieldSettingsHelper
   */
  protected $fieldSettingsHelper;

  /**
   * The TempStore service.
   *
   * This service stores temporary data to be used across HTTP requests.
   *
   * @var \Drupal\ajax_comments\TempStore
   */
  protected $tempStore;

  /**
   * A request stack symfony instance.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * Constructs a new CommentForm.
   *
   *@param \Drupal\Core\Entity\EntityRepositoryInterface $entity_repository
   *   The entity repository.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack.
   * @param \Drupal\Core\Routing\CurrentRouteMatch $current_route_match
   *   The CurrentRouteMatch service.
   * @param \Drupal\ajax_comments\FieldSettingsHelper $field_settings_helper
   *   The FieldSettingsHelper service.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entity_type_bundle_info
   *   The entity type bundle service.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   * @param \Drupal\ajax_comments\TempStore $temp_store
   *   The TempStore service.
   */
  public function __construct(EntityRepositoryInterface $entity_repository, AccountInterface $current_user, RendererInterface $renderer, EntityTypeBundleInfoInterface $entity_type_bundle_info = NULL, TimeInterface $time = NULL, EntityFieldManagerInterface $entity_field_manager = NULL, RequestStack $request_stack, CurrentRouteMatch $current_route_match, FieldSettingsHelper $field_settings_helper, TempStore $temp_store) {
    parent::__construct($entity_repository, $current_user, $renderer, $entity_type_bundle_info, $time, $entity_field_manager);
    $this->requestStack = $request_stack;
    $this->currentRouteMatch = $current_route_match;
    $this->fieldSettingsHelper = $field_settings_helper;
    $this->tempStore = $temp_store;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity.repository'),
      $container->get('current_user'),
      $container->get('renderer'),
      $container->get('entity_type.bundle.info'),
      $container->get('datetime.time'),
      $container->get('entity_field.manager'),
      $container->get('request_stack'),
      $container->get('current_route_match'),
      $container->get('ajax_comments.field_settings_helper'),
      $container->get('ajax_comments.temp_store')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);
    $request = $this->requestStack->getCurrentRequest();
    $is_ajax = Utility::isAjaxRequest($request, $form_state->getUserInput());
    $route_name = $this->currentRouteMatch->getRouteName();

    // If this is an ajax request, ensure that id attributes are generated
    // as unique.
    if ($is_ajax) {
      Html::setIsAjax(TRUE);
    }

    // Ajax replies to other comments should happen on the canonical entity page
    // (note this functionality has not been ported to D8, yet).
    // If the user is on the standalone comment reply page or comment edit page,
    // it means JavaScript is disabled or the ajax functionality is not working.
    // Do not proceed with the form alter.
    if (in_array($this->currentRouteMatch->getRouteName(), ['comment.reply', 'entity.comment.edit_form'])) {
      return $form;
    }

    /** @var \Drupal\comment\CommentInterface $comment */
    $comment = $form_state->getFormObject()->getEntity();

    /** @var \Drupal\ajax_comments\TempStore $tempStore */
    $tempStore = \Drupal::service('ajax_comments.temp_store');
    $view_mode = $tempStore->getViewMode($comment->getCommentedEntity()->getEntityType()->getLabel()->getUntranslatedString());

    // Check to see if this comment field uses ajax comments.
    $comment_formatter = $this->fieldSettingsHelper->getFieldFormatterFromComment($comment, $view_mode);
    if (!empty($comment_formatter) && !$this->fieldSettingsHelper->isEnabled($comment_formatter)) {
      // If not using Ajax Comments, return the unmodified form.
      return $form;
    }

    /** @var \Drupal\Core\Entity\ContentEntityInterface $commented_entity */
    $commented_entity = $comment->getCommentedEntity();
    $field_name = $comment->getFieldName();

    $cid = $comment->id() ? $comment->id() : 0;
    $pid = $comment->get('pid')->target_id ? $comment->get('pid')->target_id : 0;
    $id = 'ajax-comments-reply-form-' . $commented_entity->getEntityTypeId() . '-' . $commented_entity->id() . '-' . $field_name . '-' . $pid . '-' . $cid;
    $form['#attributes']['id'] = Html::getUniqueId($id);

    // Add the form's id as a hidden input so we can
    // access it in the controller.
    $form['form_html_id'] = [
      '#type' => 'hidden',
      '#value' => $form['#attributes']['id'],
    ];

    // If this is an instance of the form that was submitted (not a child
    // form on a comment field attached to this comment), then
    // update the temp store values while rebuilding the form, if necessary.
    $this->tempStore->processForm($request, $form, $form_state);

    if ($is_ajax && in_array($route_name, ['ajax_comments.edit', 'ajax_comments.reply'])) {
      $wrapper_html_id = $this->tempStore->getSelectorValue($request, 'wrapper_html_id');
    }
    else {
      $wrapper_html_id = Utility::getWrapperIdFromEntity($commented_entity, $field_name);
    }
    // Add the wrapping fields's HTML id as a hidden input
    // so we can access it in the controller.
    // NOTE: This field needs to be declared here, and the only the #value
    // property overridden in $this->setWrapperId(). Otherwise, if the field
    // were NOT declared here but rather only in $this->setWrapperId(), then
    // the hidden input element in the markup will not be generated correctly
    // when $this->setWrapperId() is called from $this->validateForm().
    $form['wrapper_html_id'] = [
      '#type' => 'hidden',
      '#value' => $wrapper_html_id,
    ];
    // Add the wrapping fields's HTML id.
    $this->setWrapperId($form, $wrapper_html_id);

    return $form;
  }

  /**
   * Set the wrapper id on the hidden element and the #ajax button properties.
   *
   * @param array $form
   *   The form array.
   * @param string $wrapper_html_id
   *   The value for the wrapper id.
   */
  protected function setWrapperId(&$form, $wrapper_html_id) {
    // Add the wrapping fields's HTML id as a hidden input
    // so we can access it in the controller.
    $form['wrapper_html_id']['#value'] = $wrapper_html_id;

    $form['actions']['submit']['#ajax']['wrapper'] = $wrapper_html_id;
    if (isset($form['actions']['cancel']['#ajax'])) {
      $form['actions']['cancel']['#ajax']['wrapper'] = $wrapper_html_id;
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function actions(array $form, FormStateInterface $form_state) {
    $element = parent::actions($form, $form_state);

    // Populate the comment-specific variables.
    /** @var \Drupal\comment\CommentInterface $comment */
    $comment = $form_state->getFormObject()->getEntity();
   /** @var \Drupal\ajax_comments\TempStore $tempStore */
    $tempStore = \Drupal::service('ajax_comments.temp_store');
    $view_mode = $tempStore->getViewMode($comment->getCommentedEntity()->getEntityType()->getLabel()->getUntranslatedString());

    $comment_formatter = $this->fieldSettingsHelper->getFieldFormatterFromComment($comment, $view_mode);
    if (!empty($comment_formatter) && !$this->fieldSettingsHelper->isEnabled($comment_formatter)) {
      // If not using Ajax Comments, return the unmodified element.
      return $element;
    }
    /** @var \Drupal\Core\Entity\EntityInterface $commented_entity */
    $commented_entity = $comment->getCommentedEntity();
    $field_name = $comment->getFieldName();
    $cid = $comment->id() ? $comment->id() : 0;
    $pid = $comment->get('pid')->target_id ? $comment->get('pid')->target_id : NULL;

    // Build the #ajax array.
    $ajax = [
      // Due to D8 core comments' use of #lazy_builder, setting a 'callback'
      // here won't work. The Drupal 8 form ajax callback functionality
      // relies on FormBuilder::buildForm() throwing an FormAjaxException()
      // during processing. The exception would be caught in Symfony's
      // HttpKernel::handle() method, which handles the exception and gets
      // responses from event subscribers, in this case FormAjaxSubscriber.
      // However, #lazy_builder causes the comment form to be built on a
      // separate, subsequent request, which causes HttpKernel::handle()
      // to be unable to catch the FormAjaxException. Using an ajax 'url'
      // instead of a callback avoids this issue.
      // The ajax URL varies based on context, so set a placeholder and
      // override below.
      'url' => NULL,
      // We need to wait for ajax_comments_entity_display_build_alter() to run
      // so that we can populate the $form['wrapper_html_id'] in
      // $this->buildForm(), so we need to set this to a NULL placeholder and
      // update the value in $this->buildForm() as well.
      'wrapper' => NULL,
      'method' => 'replace',
      'effect' => 'fade',
    ];

    // Build the ajax submit URLs.
    $ajax_new_comment_url = Url::fromRoute(
      'ajax_comments.add',
      [
        'entity_type' => $commented_entity->getEntityTypeId(),
        'entity' => $commented_entity->id(),
        'field_name' => $field_name,
        'pid' => $pid,
      ]
    );
    $ajax_edit_comment_url = Url::fromRoute(
      'ajax_comments.save',
      [
        'comment' => $cid,
      ]
    );
    $ajax_comment_reply_url = Url::fromRoute(
      'ajax_comments.save_reply',
      [
        'entity_type' => $commented_entity->getEntityTypeId(),
        'entity' => $commented_entity->id(),
        'field_name' => $field_name,
        'pid' => $pid,
      ]
    );

    // Build the cancel button render array.
    $cancel = [
      '#type' => 'button',
      '#value' => t('Cancel'),
      '#access' => TRUE,
      '#ajax' => [
        'url' => Url::fromRoute(
          'ajax_comments.cancel',
          [
            'cid' => $cid,
          ]
        ),
        // We need to wait for ajax_comments_entity_display_build_alter() to run
        // so that we can populate the $form['wrapper_html_id'] in
        // $this->buildForm(), so we need to set this to a NULL placeholder and
        // update the value in $this->buildForm() as well.
        'wrapper' => NULL,
        'method' => 'replace',
        'effect' => 'fade',
      ],
    ];

    // The form actions will vary based on the route
    // that is requesting this form.
    $request = $this->requestStack->getCurrentRequest();
    $route_name = RouteMatch::createFromRequest($request)->getRouteName();
    $editing = !empty($form_state->get('editing'));

    switch ($route_name) {
      case 'entity.comment.edit_form':
        // If we're on the standalone comment edit page (/comment/{cid}/edit),
        // don't add the ajax behavior.
        break;

      case 'ajax_comments.edit':
        $element['submit']['#ajax'] = $ajax;
        $element['submit']['#ajax']['url'] = $ajax_edit_comment_url;
        $element['cancel'] = $cancel;

        break;

      case 'ajax_comments.save':
        $element['submit']['#ajax'] = $ajax;
        // If the user attempted to submit the form but there were errors,
        // rebuild the form used at the 'ajax_comments.edit' route.
        if ($editing) {
          $element['submit']['#ajax']['url'] = $ajax_edit_comment_url;
          $element['cancel'] = $cancel;
        }
        // Otherwise, rebuild the 'add comment' form during rebuild of the
        // comment field.
        else {
          $element['submit']['#ajax']['url'] = $ajax_new_comment_url;
        }
        break;

      case 'ajax_comments.reply':
        $element['submit']['#ajax'] = $ajax;
        $element['submit']['#ajax']['url'] = $ajax_comment_reply_url;
        $element['cancel'] = $cancel;
        break;

      case 'ajax_comments.save_reply':
        $element['submit']['#ajax'] = $ajax;
        // If the user attempted to submit the form but there were errors,
        // rebuild the form used at the 'ajax_comments.reply' route.
        if ($editing) {
          $element['submit']['#ajax']['url'] = $ajax_comment_reply_url;
          $element['cancel'] = $cancel;
        }
        // Otherwise, rebuild the 'add comment' form during rebuild of the
        // comment field.
        else {
          $element['submit']['#ajax']['url'] = $ajax_new_comment_url;
        }
        break;

      default:
        $element['submit']['#ajax'] = $ajax;
        $element['submit']['#ajax']['url'] = $ajax_new_comment_url;

        break;
    }

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);

    /** @var \Drupal\comment\CommentInterface $comment */
    $comment = $form_state->getFormObject()->getEntity();
    $comment_formatter = $this->fieldSettingsHelper->getFieldFormatterFromComment($comment, 'full');
    if ($comment_formatter && !$this->fieldSettingsHelper->isEnabled($comment_formatter)) {
      // If not using Ajax Comments, do not process further.
      return;
    }

    $request = $this->requestStack->getCurrentRequest();
    $route_name = $this->currentRouteMatch->getRouteName();
    $this->tempStore->processForm($request, $form, $form_state, $is_validating = TRUE);
    if ($form_state->hasAnyErrors() && in_array($route_name, ['ajax_comments.save', 'ajax_comments.save_reply'])) {
      // If we are trying to save an edit to an existing comment, and there is
      // a form error, set the wrapper element ID back to its original value,
      // because we haven't executed a complete replacement of the wrapper
      // element in this case.
      $wrapper_html_id = $this->tempStore->getSelectorValue($request, 'wrapper_html_id');
      $this->setWrapperId($form, $wrapper_html_id);
    }
  }

  /**
   * Override the redirect set by \Drupal\comment\CommentForm::save().
   *
   * Drupal needs to redirect the form back to itself so that processing
   * completes and the new comments appears in the markup returned by the
   * ajax response. If we merely unset the redirect to the node page, the new
   * comment will not appear until the next page refresh.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function save(array $form, FormStateInterface $form_state) {
    parent::save($form, $form_state);
    /** @var \Drupal\comment\CommentInterface $comment */
    $comment = $form_state->getFormObject()->getEntity();
    /** @var \Drupal\ajax_comments\TempStore $tempStore */
    $tempStore = \Drupal::service('ajax_comments.temp_store');
    $view_mode = $tempStore->getViewMode($comment->getCommentedEntity()->getEntityType()->getLabel()->getUntranslatedString());

    $comment_formatter = $this->fieldSettingsHelper->getFieldFormatterFromComment($comment, $view_mode);
    if (!empty($comment_formatter) && !$this->fieldSettingsHelper->isEnabled($comment_formatter)) {
      // If not using Ajax Comments, do not change the redirect.
      return;
    }

    // Save the comment id in the private tempStore, so that the controller
    // can access it in a subsequent HTTP request.
    $this->tempStore->setCid($comment->id());

    // Code adapted from FormSubmitter::redirectForm().
    $request = $this->requestStack->getCurrentRequest();
    $route_name = RouteMatch::createFromRequest($request)->getRouteName();
    if (!in_array($route_name, ['entity.comment.edit_form', 'comment.reply'])) {
      $form_state->setRedirect(
        '<current>',
        [],
        ['query' => $request->query->all(), 'absolute' => TRUE]
      );
    }
  }

}
