<?php

namespace Drupal\flag\Plugin\views\field;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Link;
use Drupal\flag\Plugin\ActionLink\FormEntryInterface;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Entity\EntityInterface;

/**
 * Provides a views field to flag or unflag the selected content.
 *
 * Unlike FlagViewsFlaggedField, this views field handler provides an
 * actionable link to flag or unflag the selected content.
 *
 * @ViewsField("flag_link")
 */
class FlagViewsLinkField extends FieldPluginBase {

  /**
   * A helper method to retrieve the flag entity from the views relationship.
   *
   * @return FlagInterface|null
   *   The flag selected by the views relationship.
   */
  public function getFlag() {
    // When editing a view it's possible to delete the relationship (either by
    // error or to later recreate it), so we have to guard against a missing
    // one.
    if (isset($this->view->relationship[$this->options['relationship']])) {
      return $this->view->relationship[$this->options['relationship']]->getFlag();
    }

    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();

    // Set the default relationship handler. The first instance of the
    // FlagViewsRelationship should always have the id "flag_relationship", so
    // we set that as the default.
    $options['relationship'] = array('default' => 'flag_relationship');

    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    $form['relationship']['#default_value'] = $this->options['relationship'];

    parent::buildOptionsForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function query() {
    // Intentionally do nothing here since we're only providing a link and not
    // querying against a real table column.
  }

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values) {
    $entity = $this->getParentRelationshipEntity($values);
    return $this->renderLink($entity, $values);
  }

  /**
   * Returns the entity for this field's relationship's parent relationship.
   *
   * For example, if this field's flag relationship is itself on a node, then
   * this will return the node entity for the current row.
   *
   * @param ResultRow $values
   *   The current result row.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   */
  protected function getParentRelationshipEntity(ResultRow $values) {
    $relationship_id = $this->options['relationship'];
    $relationship_handler = $this->view->display_handler->handlers['relationship'][$relationship_id];
    $parent_relationship_id = $relationship_handler->options['relationship'];

    if ($parent_relationship_id == 'none') {
      return $values->_entity;
    }
    elseif (isset($values->_relationship_entities[$parent_relationship_id])) {
      return $values->_relationship_entities[$parent_relationship_id];
    }
  }

  /**
   * Creates a render array for flag links.
   *
   * @param EntityInterface $entity
   *   The entity object.
   * @param ResultRow $values
   *   The current result row.
   *
   * @return array|string
   *   The render array for the flag link.
   */
  protected function renderLink(EntityInterface $entity, ResultRow $values) {
    // Output nothing as there is no flag.
    // For an 'empty text' option use the default 'No results behavior'
    // option provided by Views.
    if (empty($entity)) {
      return '';
    }

    $flag = $this->getFlag();
    $link_type_plugin = $flag->getLinkTypePlugin();

    $link = $link_type_plugin->getAsLink($flag, $entity);

    $renderable = $link->toRenderable();

    if ($link_type_plugin instanceof FormEntryInterface) {
      // Check if form should be in a modal or dialog.
      $configuration = $link_type_plugin->getConfiguration();
      if ($configuration['form_behavior'] !== 'default') {
        $renderable['#attached']['library'][] = 'core/drupal.ajax';
        $renderable['#attributes']['class'][] = 'use-ajax';
        $renderable['#attributes']['data-dialog-type'] = $configuration['form_behavior'];
        $renderable['#attributes']['data-dialog-options'] = Json::encode([
          'width' => 'auto',
        ]);
      }
    }

    return $renderable;
  }

}
