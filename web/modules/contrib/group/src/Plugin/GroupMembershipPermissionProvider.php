<?php

namespace Drupal\group\Plugin;

/**
 * Provides group permissions for group_membership GroupContent entities.
 */
class GroupMembershipPermissionProvider extends GroupContentPermissionProvider {

  /**
   * {@inheritdoc}
   */
  public function getRelationUpdatePermission($scope = 'any') {
    // Update any is handled by the admin permission.
    if ($scope === 'own') {
      return parent::getRelationUpdatePermission($scope);
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function getRelationDeletePermission($scope = 'any') {
    // Delete any is handled by the admin permission.
    if ($scope === 'own') {
      return 'leave group';
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function getRelationCreatePermission() {
    // Create is handled by the admin permission.
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function buildPermissions() {
    $permissions = parent::buildPermissions();

    $permissions['join group'] = [
      'title' => 'Join group',
      'allowed for' => ['outsider'],
    ];

    // Update the labels of the default permissions.
    $permissions[$this->getAdminPermission()]['title'] = 'Administer group members';
    $permissions[$this->getRelationViewPermission()]['title'] = 'View individual group members';
    $permissions[$this->getRelationUpdatePermission('own')]['title'] = 'Edit own membership';
    $permissions[$this->getRelationDeletePermission('own')]['title'] = 'Leave group';

    // Update the audience of the default permissions.
    $permissions[$this->getRelationUpdatePermission('own')]['allowed for'] = ['member'];
    $permissions[$this->getRelationDeletePermission('own')]['allowed for'] = ['member'];

    return $permissions;
  }

}
