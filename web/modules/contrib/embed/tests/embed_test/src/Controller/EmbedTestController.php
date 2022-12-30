<?php

namespace Drupal\embed_test\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Render\HtmlResponse;
use Drupal\editor\EditorInterface;
use Drupal\embed\EmbedButtonInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Returns responses for Embed Test module routes.
 */
class EmbedTestController extends ControllerBase {

  public function testAccess(Request $request, EditorInterface $editor, EmbedButtonInterface $embed_button) {
    $text = $request->get('value');

    $response = new HtmlResponse([
      '#markup' => $text,
      '#cache' => [
        'contexts' => ['url.query_args:value'],
      ],
    ]);

    if ($text == '') {
      $response->setStatusCode(404);
    }

    return $response;
  }

  /**
   * Return CSRF token.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   CSRF token.
   */
  public function getCsrfToken() {
    return new JsonResponse(\Drupal::csrfToken()->get('X-Drupal-EmbedPreview-CSRF-Token'));
  }

}
