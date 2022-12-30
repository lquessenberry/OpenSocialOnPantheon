<?php

namespace Drupal\ajax_comments\Form;

use Drupal\ajax_comments\TempStore;
use Drupal\ajax_comments\Utility;
use Drupal\comment\CommentInterface;
use Drupal\comment\Form\DeleteForm;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides ajax enhancements to core Comment delete form.
 *
 * @package Drupal\ajax_comments
 */
class AjaxCommentsDeleteForm extends DeleteForm {

  /**
   * The TempStore service.
   *
   * This service stores temporary data to be used across HTTP requests.
   *
   * @var \Drupal\ajax_comments\TempStore
   */
  protected $tempStore;

  /**
   * Constructs an AjaxCommentsDeleteForm object.
   *
   * @param \Drupal\Core\Entity\EntityRepositoryInterface $entity_repository
   *   The entity manager.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entity_type_bundle_info
   *   The entity type bundle service.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   * @param \Drupal\ajax_comments\TempStore $temp_store
   *   The TempStore service.
   */
  public function __construct(EntityRepositoryInterface $entity_repository, EntityTypeBundleInfoInterface $entity_type_bundle_info, TimeInterface $time, TempStore $temp_store) {
    parent::__construct($entity_repository, $entity_type_bundle_info, $time);
    $this->tempStore = $temp_store;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity.repository'),
      $container->get('entity_type.bundle.info'),
      $container->get('datetime.time'),
      $container->get('ajax_comments.temp_store')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, CommentInterface $comment = NULL) {
    $form = parent::buildForm($form, $form_state);

    // Check if this form is being loaded through ajax or as a modal dialog.
    $request = $this->requestStack->getCurrentRequest();
    $is_ajax_request = Utility::isAjaxRequest($request, $form_state->getUserInput());
    $is_modal_request = Utility::isModalRequest($request);
    if ($is_modal_request || $is_ajax_request) {
      // In some circumstances the $comment object needs to be initialized.
      if (empty($comment)) {
        $comment = $form_state->getFormObject()->getEntity();
      }

      // Get the selectors from the request.
      $this->tempStore->getSelectors($request, $overwrite = TRUE);
      $wrapper_html_id = $this->tempStore->getSelectorValue($request, 'wrapper_html_id');

      // Add the wrapping fields's HTML id as a hidden input
      // so we can access it in the controller.
      $form['wrapper_html_id'] = [
        '#type' => 'hidden',
        '#value' => $wrapper_html_id,
      ];

      // Add a class to target this form in JavaScript.
      $form['#attributes']['class'][] = 'ajax-comments';

      // Add a class to the cancel button to trigger modal dialog close.
      $form['actions']['cancel']['#attributes']['class'][] = 'dialog-cancel';

      // Set up this form to ajax submit so that we aren't redirected to
      // another page upon clicking the 'Delete' button.
      $form['actions']['submit']['#ajax'] = [
        'url' => Url::fromRoute(
          'ajax_comments.delete',
          [
            'comment' => $comment->id(),
          ]
        ),
        'wrapper' => $wrapper_html_id,
        'method' => 'replace',
        'effect' => 'fade',
      ];
    }
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);
    $request = $this->requestStack->getCurrentRequest();
    // Disable the form redirect if the delete confirmation form was loaded
    // through ajax in a modal dialog box, but allow redirect if the user
    // manually opens the link in a new window or tab (e.g., /comment/1/delete).
    if (Utility::isAjaxRequest($request)) {
      $form_state->disableRedirect();
    }
  }

}
