<?php

namespace Drupal\override_node_options\Access\FieldAccess;

use Drupal\Core\Access\AccessResult;

class GrantAuthoredByAccess extends AbstractFieldAccessOverride implements FieldAccessOverrideInterface {

  public static function access(array &$grants, array $context) {
    self::$context = $context;

    if (self::hasNodeEditPermission() && self::isFieldName('uid')) {
      $bundle = $context['items']->getEntity()->bundle();
      $grants[':default'] = AccessResult::allowedIfHasPermissions(
        $context['account'],
        [
          "override $bundle authored by option",
          'override all authored by option',
        ],
        'OR'
      );
    }
  }

}
