<?php

namespace Drupal\override_node_options\Access\FieldAccess;

abstract class AbstractFieldAccessOverride implements FieldAccessOverrideInterface {

  /**
   * @var array
   */
  protected static $context;

  protected static function hasNodeEditPermission() {
    $entityType = self::$context['field_definition']->getTargetEntityTypeId();

    return $entityType == 'node'
      && self::$context['operation'] == 'edit'
      && !self::$context['account']->hasPermission('administer nodes');
  }

  protected static function isFieldName($fieldName) {
    return self::$context['field_definition']->getName() == $fieldName;
  }

}
