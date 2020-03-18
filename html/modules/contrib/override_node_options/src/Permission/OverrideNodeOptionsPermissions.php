<?php

namespace Drupal\override_node_options\Permission;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\node\Entity\NodeType;

/**
 * Provides dynamic override permissions for nodes of different types.
 */
class OverrideNodeOptionsPermissions {

  use StringTranslationTrait;

  /**
   * Returns an array of additional permissions.
   *
   * @return array
   *   An array of permissions.
   */
  public function permissions() {
    $permissions = [];

    if (\Drupal::config('override_node_options.settings')->get('general_permissions')) {
      $this->addGeneralPermissions($permissions);
    }

    if (\Drupal::config('override_node_options.settings')->get('specific_permissions')) {
      $this->addSpecificPermissions($permissions);
    }

    return $permissions;
  }

  /**
   * Add general permissions.
   *
   * @param array $permissions
   *   The permissions array, passed by reference.
   */
  private function addGeneralPermissions(array &$permissions) {
    $permissions['override all published option'] = [
      'title' => $this->t('Override all published options.'),
    ];

    $permissions['override all promote to front page option'] = [
      'title' => $this->t('Override all promote to front page options.'),
    ];

    $permissions['override all sticky option'] = [
      'title' => $this->t('Override all sticky options.'),
    ];

    $permissions['override all revision option'] = [
      'title' => $this->t('Override all revision option.'),
    ];

    $permissions['override all authored by option'] = [
      'title' => $this->t('Override all authored by option.'),
    ];

    $permissions['override all authored on option'] = [
      'title' => $this->t('Override all authored on option.'),
    ];
  }

  /**
   * Add node type specific permissions.
   *
   * @param array $permissions
   *   The permissions array, passed by reference.
   */
  private function addSpecificPermissions(array &$permissions) {
    /** @var \Drupal\node\Entity\NodeType $type */
    foreach (NodeType::loadMultiple() as $type) {
      $id = $type->id();
      $name = $type->label();

      $permissions["override $id published option"] = [
        'title' => $this->t("Override %type_name published option.", ["%type_name" => $name]),
      ];

      $permissions["override $id promote to front page option"] = [
        'title' => $this->t("Override %type_name promote to front page option.", ["%type_name" => $name]),
      ];

      $permissions["override $id sticky option"] = [
        'title' => $this->t("Override %type_name sticky option.", ["%type_name" => $name]),
      ];

      $permissions["override $id revision option"] = [
        'title' => $this->t("Override %type_name revision option.", ["%type_name" => $name]),
      ];

      $permissions["override $id revision log entry"] = [
        'title' => $this->t("Enter %type_name revision log entry.", ["%type_name" => $name]),
      ];

      $permissions["override $id authored on option"] = [
        'title' => $this->t("Override %type_name authored on option.", ["%type_name" => $name]),
      ];

      $permissions["override $id authored by option"] = [
        'title' => $this->t("Override %type_name authored by option.", ["%type_name" => $name]),
      ];
    }
  }

}
