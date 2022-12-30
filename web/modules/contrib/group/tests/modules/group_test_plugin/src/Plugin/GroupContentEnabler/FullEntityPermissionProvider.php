<?php

namespace Drupal\group_test_plugin\Plugin\GroupContentEnabler;

use Drupal\group\Plugin\GroupContentPermissionProvider;

/**
 * Provides all possible permissions.
 */
class FullEntityPermissionProvider extends GroupContentPermissionProvider {

  /**
   * {@inheritdoc}
   */
  public function getEntityViewPermission($scope = 'any') {
    if ($this->definesEntityPermissions) {
      if ($this->implementsOwnerInterface || $scope === 'any') {
        return "view $scope $this->pluginId entity";
      }
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function getEntityViewUnpublishedPermission($scope = 'any') {
    if ($this->definesEntityPermissions) {
      if ($this->implementsPublishedInterface) {
        if ($this->implementsOwnerInterface || $scope === 'any') {
          return "view $scope unpublished $this->pluginId entity";
        }
      }
    }
    return FALSE;
  }

}
