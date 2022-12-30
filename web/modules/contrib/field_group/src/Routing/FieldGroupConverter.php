<?php

namespace Drupal\field_group\Routing;

use Symfony\Component\Routing\Route;
use Drupal\Core\ParamConverter\ParamConverterInterface;

/**
 * Parameter converter for upcasting fieldgroup config ids to fieldgroup object.
 */
class FieldGroupConverter implements ParamConverterInterface {

  /**
   * {@inheritdoc}
   */
  public function applies($definition, $name, Route $route) {
    return isset($definition['type']) && $definition['type'] == 'field_group';
  }

  /**
   * {@inheritdoc}
   */
  public function convert($value, $definition, $name, array $defaults) {
    $identifiers = explode('.', $value);
    if (count($identifiers) != 5) {
      return;
    }

    return field_group_load_field_group($identifiers[4], $identifiers[0], $identifiers[1], $identifiers[2], $identifiers[3]);
  }

}
