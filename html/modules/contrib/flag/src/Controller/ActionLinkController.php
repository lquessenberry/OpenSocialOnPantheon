<?php

namespace Drupal\flag\Controller;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\EventSubscriber\MainContentViewSubscriber;
use Drupal\Core\Render\RendererInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\flag\FlagInterface;
use Drupal\flag\FlagServiceInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Provides a controller to flag and unflag when routed from a normal link.
 */
class ActionLinkController extends ControllerBase implements ContainerInjectionInterface {

  /**
   * The flag service.
   *
   * @var \Drupal\flag\FlagServiceInterface
   */
  protected $flagService;

  /**
   * The renderer service.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * Constructor.
   *
   * @param FlagServiceInterface $flag
   *   The flag service.
   */
  public function __construct(FlagServiceInterface $flag, RendererInterface $renderer) {
    $this->flagService = $flag;
    $this->renderer = $renderer;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('flag'),
      $container->get('renderer')
    );
  }

  /**
   * Performs a flagging when called via a route.
   *
   * @param \Drupal\flag\FlagInterface $flag
   *   The flag entity.
   * @param int $entity_id
   *   The flaggable entity ID.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   The response object.
   *
   * @see \Drupal\flag\Plugin\Reload
   */
  public function flag(FlagInterface $flag, $entity_id, Request $request) {
    /* @var \Drupal\Core\Entity\EntityInterface $entity */
    $entity = $this->flagService->getFlaggableById($flag, $entity_id);

    try {
      $this->flagService->flag($flag, $entity);
    }
    catch (\LogicException $e) {
      // Fail silently so we return to the entity, which will show an updated
      // link for the existing state of the flag.
    }

    return $this->generateResponse($flag, $entity, $request);
  }

  /**
   * Performs a flagging when called via a route.
   *
   * @param \Drupal\flag\FlagInterface $flag
   *   The flag entity.
   * @param int $entity_id
   *   The flaggable entity ID.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   The response object.
   *
   * @see \Drupal\flag\Plugin\Reload
   */
  public function unflag(FlagInterface $flag, $entity_id, Request $request) {
    /* @var \Drupal\Core\Entity\EntityInterface $entity */
    $entity = $this->flagService->getFlaggableById($flag, $entity_id);

    try {
      $this->flagService->unflag($flag, $entity);
    }
    catch (\LogicException $e) {
      // Fail silently so we return to the entity, which will show an updated
      // link for the existing state of the flag.
    }

    return $this->generateResponse($flag, $entity, $request);
  }

  /**
   * Generates a response object after handing the un/flag request.
   *
   * Depending on the wrapper format of the request, it will either redirect
   * or return an ajax response.
   *
   * @param \Drupal\flag\FlagInterface $flag
   *   The flag entity.
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity object.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse|\Symfony\Component\HttpFoundation\RedirectResponse
   *   The response object.
   */
  protected function generateResponse(FlagInterface $flag, EntityInterface $entity, Request $request) {
    if ($request->get(MainContentViewSubscriber::WRAPPER_FORMAT) == 'drupal_ajax') {
      // Create a new AJAX response.
      $response = new AjaxResponse();

      // Get the link type plugin.
      $link_type = $flag->getLinkTypePlugin();

      // Generate the link render array.
      $link = $link_type->getAsFlagLink($flag, $entity);

      // Generate a CSS selector to use in a JQuery Replace command.
      $selector = '.flag-' . $flag->id() . '-' . $entity->id();

      // Create a new JQuery Replace command to update the link display.
      $replace = new ReplaceCommand($selector, $this->renderer->renderPlain($link));
      $response->addCommand($replace);

      return $response;
    }
    else {
      // Redirect back to the entity. A passed in destination query parameter
      // will automatically override this.
      $url_info = $entity->toUrl();
      return $this->redirect($url_info->getRouteName(), $url_info->getRouteParameters());
    }

  }

}
