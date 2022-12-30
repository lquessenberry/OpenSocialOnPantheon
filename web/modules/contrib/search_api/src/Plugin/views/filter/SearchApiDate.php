<?php

namespace Drupal\search_api\Plugin\views\filter;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\views\Plugin\views\filter\Date;

/**
 * Defines a filter for filtering on dates.
 *
 * @ingroup views_filter_handlers
 *
 * @ViewsFilter("search_api_date")
 */
class SearchApiDate extends Date {

  use SearchApiFilterTrait;

  /**
   * The time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface|null
   */
  protected $timeService;

  /**
   * Retrieves the time service.
   *
   * @return \Drupal\Component\Datetime\TimeInterface
   *   The time service.
   */
  public function getTimeService() {
    return $this->timeService ?: \Drupal::time();
  }

  /**
   * Sets the time service.
   *
   * @param \Drupal\Component\Datetime\TimeInterface $time_service
   *   The new time service.
   *
   * @return $this
   */
  public function setTimeService(TimeInterface $time_service) {
    $this->timeService = $time_service;
    return $this;
  }

  /**
   * Defines the operators supported by this filter.
   *
   * @return array[]
   *   An associative array of operators, keyed by operator ID, with information
   *   about that operator:
   *   - title: The full title of the operator (translated).
   *   - short: The short title of the operator (translated).
   *   - method: The method to call for this operator in query().
   *   - values: The number of values that this operator expects/needs.
   */
  public function operators() {
    $operators = parent::operators();
    unset($operators['regular_expression']);
    return $operators;
  }

  /**
   * {@inheritdoc}
   */
  public function acceptExposedInput($input) {
    if (empty($this->options['exposed'])) {
      return TRUE;
    }

    $return = parent::acceptExposedInput($input);

    if (!$return) {
      // Override for the "(not) empty" operators.
      $operators = $this->operators();
      if ($operators[$this->operator]['values'] == 0) {
        return TRUE;
      }
    }

    return $return;
  }

  /**
   * {@inheritdoc}
   */
  protected function opBetween($field) {
    if ($this->value['type'] == 'offset') {
      $time = $this->getTimeService()->getRequestTime();
      $a = strtotime($this->value['min'], $time);
      $b = strtotime($this->value['max'], $time);
    }
    else {
      $a = intval(strtotime($this->value['min'], 0));
      $b = intval(strtotime($this->value['max'] . ' +1 day', 0)) - 1;
    }
    $real_field = $this->realField;
    $operator = strtoupper($this->operator);
    $group = $this->options['group'];
    $this->getQuery()->addCondition($real_field, [$a, $b], $operator, $group);
  }

  /**
   * Filters by a simple operator (=, !=, >, etc.).
   *
   * @param string $field
   *   The views field.
   */
  protected function opSimple($field) {
    $value = intval(strtotime($this->value['value'], 0));
    if (($this->value['type'] ?? '') == 'offset') {
      $time = $this->getTimeService()->getRequestTime();
      $value = strtotime($this->value['value'], $time);
    }

    $this->getQuery()->addCondition($this->realField, $value, $this->operator, $this->options['group']);
  }

}
