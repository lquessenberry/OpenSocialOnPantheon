<?php

namespace Drupal\override_node_options\Access\FieldAccess;

interface FieldAccessOverrideInterface {

  public static function access(array &$grants, array $context);

}
