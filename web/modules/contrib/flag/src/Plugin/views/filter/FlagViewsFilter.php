<?php

namespace Drupal\flag\Plugin\views\filter;

use Drupal\views\Plugin\views\filter\BooleanOperator;
use Drupal\Core\Form\FormStateInterface;

/**
 * Filters content by its flagging status in a view.
 *
 * @ViewsFilter("flag_filter")
 */
class FlagViewsFilter extends BooleanOperator {

  /**
   * {@inheritdoc}
   */
  public function defineOptions() {
    $options = parent::defineOptions();
    $options['value'] = array('default' => 1);
    $options['relationship'] = array('default' => 'flag_relationship');

    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);

    $form['value']['#type'] = 'radios';
    $form['value']['#title'] = t('Status');
    $form['value']['#options'] = [
      1 => $this->t('Flagged'),
      0 => $this->t('Not flagged'),
      // @todo Find out what in the hell filter type ALL is supposed to do.
      // 'All' => t('All'),
    ];
    $form['value']['#default_value'] = empty($this->options['value']) ? FALSE : $this->options['value'];
    $form['value']['#description'] = '<p>' . $this->t('This filter is only needed if the relationship used has the "Include only flagged content" option <strong>unchecked</strong>. Otherwise, this filter is useless, because all records are already limited to flagged content.') . '</p><p>' . $this->t('By choosing <em>Not flagged</em>, it is possible to create a list of content <a href="@unflagged-url">that is specifically not flagged</a>.', array('@unflagged-url' => 'http://drupal.org/node/299335')) . '</p>';

    $form['relationship']['#default_value'] = $this->options['relationship'];
  }

  /**
   * {@inheritdoc}
   */
  public function query() {
    $this->ensureMyTable();

    $operator = $this->options['value'] ? 'IS NOT' : 'IS';
    $operator .= ' NULL';

    $this->query->addWhere($this->options['group'], "$this->tableAlias.uid", NULL, $operator);
  }

}
