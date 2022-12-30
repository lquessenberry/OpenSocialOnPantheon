<?php

namespace Drupal\ajax_comments;

use Drupal\comment\CommentInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Display\EntityDisplayInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FormatterInterface;
use Drupal\Core\Field\FormatterPluginManager;

/**
 * Class FieldSettingsHelper.
 *
 * @package Drupal\ajax_comments
 */
class FieldSettingsHelper {

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The field formatter plugin manager service.
   *
   * @var \Drupal\Core\Field\FormatterPluginManager
   */
  protected $fieldFormatterManager;

  /**
   * AjaxCommentsFieldSettings constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   * @param \Drupal\Core\Field\FormatterPluginManager $formatter_plugin_manager
   *   The field formatter plugin manager service.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, FormatterPluginManager $formatter_plugin_manager) {
    $this->entityTypeManager = $entity_type_manager;
    $this->fieldFormatterManager = $formatter_plugin_manager;
  }

  /**
   * Get the entity view display configuration for the commented entity.
   *
   * @param \Drupal\comment\CommentInterface $comment
   *   The comment entity.
   * @param string $view_mode
   *   The current view mode.
   *
   * @return \Drupal\Core\Entity\Display\EntityDisplayInterface
   *   The entity view display configuration for the commented entity.
   */
  public function getEntityViewDisplay(CommentInterface $comment, $view_mode = 'default') {
    /** @var \Drupal\Core\Entity\ContentEntityInterface $commented_entity */
    $commented_entity = $comment->getCommentedEntity();

    // Try to load the configuration entity for the entity's
    // view display settings.
    /** @var \Drupal\Core\Entity\Display\EntityDisplayInterface $view_display */
    $view_display = $this->entityTypeManager
      ->getStorage('entity_view_display')
      ->load($commented_entity->getEntityTypeId() . '.' . $commented_entity->bundle() . '.' . $view_mode);

    // If there is no entity view display configuration for the provided
    // view mode, fall back on the default view mode.
    if (empty($view_display)) {
      $view_display = $this->entityTypeManager
        ->getStorage('entity_view_display')
        ->load($commented_entity->getEntityTypeId() . '.' . $commented_entity->bundle() . '.default');
    }

    return $view_display;
  }

  /**
   * Get the active field formatter for a comment field.
   *
   * @param \Drupal\Core\Entity\Display\EntityDisplayInterface $view_display
   *   The commented entity view display configuration.
   * @param string $field_name
   *   The machine name of the comment field.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The field configuration.
   * @param string $view_mode
   *   The current view mode.
   *
   * @return \Drupal\Core\Field\FormatterInterface
   *   The field formatter for the comment field.
   */
  public function getFieldFormatter(EntityDisplayInterface $view_display, $field_name, FieldDefinitionInterface $field_definition, $view_mode = 'default') {
    // Get the comment field display configuration from the entity's
    // view mode configuration.
    $display_options = $view_display
      ->getComponent($field_name);

    // If the field is hidden on the provided view_mode, $display_options
    // will be empty. Trying to get a field formatter instance will cause
    // an error.
    if (empty($display_options)) {
      $comment_formatter = FALSE;
    }
    else {
      // Get the formatter for the current comment field.
      /** @var \Drupal\Core\Field\FormatterInterface $comment_formatter */
      $comment_formatter = $this->fieldFormatterManager
        ->getInstance([
          'field_definition' => $field_definition,
          'view_mode' => $view_mode,
          'configuration' => $display_options,
        ]);
    }

    return $comment_formatter;
  }

  /**
   * Get the active field formatter for a comment entity.
   *
   * @param \Drupal\comment\CommentInterface $comment
   *   The comment entity.
   * @param string $view_mode
   *   The current view mode.
   *
   * @return \Drupal\Core\Field\FormatterInterface
   *   The field formatter for the current comment.
   */
  public function getFieldFormatterFromComment(CommentInterface $comment, $view_mode = 'default') {
    /** @var \Drupal\Core\Entity\ContentEntityInterface $commented_entity */
    $commented_entity = $comment->getCommentedEntity();
    $field_name = $comment->getFieldName();
    /** @var \Drupal\Core\Field\FieldDefinitionInterface $field_definition */
    $field_definition = $commented_entity->getFieldDefinition($field_name);

    // Load the configuration entity for the entity's view display settings.
    /** @var \Drupal\Core\Entity\Display\EntityDisplayInterface $view_display */
    $view_display = $this->getEntityViewDisplay($comment, $view_mode);

    // Get the formatter for the current comment field.
    /** @var \Drupal\Core\Field\FormatterInterface $comment_formatter */
    $comment_formatter = $this->getFieldFormatter($view_display, $field_name, $field_definition, $view_mode);

    return $comment_formatter;
  }

  /**
   * Determine if ajax comments is enabled for a comment field in a view mode.
   *
   * @param \Drupal\Core\Field\FormatterInterface $comment_formatter
   *   The field formatter for the comment field in the provided
   *   entity view mode.
   *
   * @return bool
   *   Whether or not ajax comments is enabled on the comment field's display
   *   settings.
   */
  public function isEnabled(FormatterInterface $comment_formatter) {
    return $comment_formatter->getThirdPartySetting('ajax_comments', 'enable_ajax_comments', '1');
  }

}
