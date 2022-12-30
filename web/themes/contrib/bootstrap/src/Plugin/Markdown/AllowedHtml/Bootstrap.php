<?php

namespace Drupal\bootstrap\Plugin\Markdown\AllowedHtml;

use Drupal\Core\Plugin\PluginBase;
use Drupal\Core\Theme\ActiveTheme;
use Drupal\markdown\Plugin\Markdown\AllowedHtmlInterface;
use Drupal\markdown\Plugin\Markdown\ParserInterface;

/**
 * Provides additional Bootstrap specific allowed HTML for Markdown.
 *
 * @MarkdownAllowedHtml(
 *   id = "bootstrap",
 *   description = @Translation("Provide common global attributes that are useful when dealing with Bootstrap specific output."),
 * )
 */
class Bootstrap extends PluginBase implements AllowedHtmlInterface {

  /**
   * {@inheritdoc}
   */
  public function allowedHtmlTags(ParserInterface $parser, ActiveTheme $activeTheme = NULL) {
    return [
      '*' => [
        'data-complete-text' => TRUE,
        'data-container' => TRUE,
        'data-content' => TRUE,
        'data-dismiss' => TRUE,
        'data-loading-text' => TRUE,
        'data-parent' => TRUE,
        'data-placement' => TRUE,
        'data-ride' => TRUE,
        'data-slide' => TRUE,
        'data-slide-to' => TRUE,
        'data-spy' => TRUE,
        'data-target' => TRUE,
        'data-toggle' => TRUE,
      ],
    ];
  }

}
