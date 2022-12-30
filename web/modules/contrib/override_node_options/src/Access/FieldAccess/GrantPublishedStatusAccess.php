<?php

namespace Drupal\override_node_options\Access\FieldAccess;

use Drupal\Core\Access\AccessResult;

class GrantPublishedStatusAccess extends AbstractFieldAccessOverride implements FieldAccessOverrideInterface {

  public static function access(array &$grants, array $context) {
    self::$context = $context;

    if (self::hasNodeEditPermission() && self::isFieldName('status')) {
      $bundle = $context['items']->getEntity()->bundle();
      $grants[':default'] = AccessResult::allowedIfHasPermissions(
        $context['account'],
        [
          "override $bundle published option",
          'override all published option',
        ],
        'OR'
      );
    }
  }

}
