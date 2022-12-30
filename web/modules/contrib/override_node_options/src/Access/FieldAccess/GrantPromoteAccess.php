<?php

namespace Drupal\override_node_options\Access\FieldAccess;

use Drupal\Core\Access\AccessResult;

class GrantPromoteAccess extends AbstractFieldAccessOverride implements FieldAccessOverrideInterface {

  public static function access(array &$grants, array $context) {
    self::$context = $context;

    if (self::hasNodeEditPermission() && self::isFieldName('promote')) {
      $bundle = $context['items']->getEntity()->bundle();
      $grants[':default'] = AccessResult::allowedIfHasPermissions(
        $context['account'],
        [
          "override $bundle promote to front page option",
          'override all promote to front page option',
        ],
        'OR'
      );
    }
  }

}
