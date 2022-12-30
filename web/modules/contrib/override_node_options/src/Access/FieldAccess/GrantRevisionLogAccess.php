<?php

namespace Drupal\override_node_options\Access\FieldAccess;

use Drupal\Core\Access\AccessResult;

class GrantRevisionLogAccess extends AbstractFieldAccessOverride implements FieldAccessOverrideInterface {

  public static function access(array &$grants, array $context) {
    self::$context = $context;

    if (self::hasNodeEditPermission() && self::isFieldName('revision_log')) {
      $bundle = $context['items']->getEntity()->bundle();
      $grants[':default'] = AccessResult::allowedIfHasPermissions(
        $context['account'],
        [
          "enter $bundle revision log entry",
          'enter all revision log entry',
        ],
        'OR'
      );
    }
  }

}
