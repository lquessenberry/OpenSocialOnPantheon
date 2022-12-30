<?php

namespace Drupal\override_node_options;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\node\Entity\NodeType;

/**
 * Provides dynamic override permissions for nodes of different types.
 */
class NodePermissions {

  use StringTranslationTrait;

  /**
   * Returns an array of additional permissions.
   *
   * @return array
   *   An array of permissions.
   */
  public function nodeTypePermissions() {
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

    $permissions['enter all revision log entry'] = [
      'title' => $this->t('Enter revision log entries for all node types.'),
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
    /** @var \Drupal\node\Entity\NodeType $node_type */
    foreach (NodeType::loadMultiple() as $node_type) {
      $type = $node_type->id();
      $label = $node_type->label();

      $permissions["override $type published option"] = [
        'title' => $this->t("Override %name published option.", ["%name" => $label]),
      ];

      $permissions["override $type promote to front page option"] = [
        'title' => $this->t("Override %name promote to front page option.", ["%name" => $label]),
      ];

      $permissions["override $type sticky option"] = [
        'title' => $this->t("Override %name sticky option.", ["%name" => $label]),
      ];

      $permissions["override $type revision option"] = [
        'title' => $this->t("Override %name revision option.", ["%name" => $label]),
      ];

      $permissions["enter $type revision log entry"] = [
        'title' => $this->t("Enter %name revision log entry.", ["%name" => $label]),
      ];

      $permissions["override $type authored on option"] = [
        'title' => $this->t("Override %name authored on option.", ["%name" => $label]),
      ];

      $permissions["override $type authored by option"] = [
        'title' => $this->t("Override %name authored by option.", ["%name" => $label]),
      ];
    }
  }

}
