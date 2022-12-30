<?php

namespace Drupal\ginvite\Plugin;

use Drupal\group\Plugin\GroupContentPermissionProvider;

/**
 * Provides group permissions for group content entities.
 */
class GroupInvitationPermissionProvider extends GroupContentPermissionProvider {

  /**
   * {@inheritdoc}
   */
  public function getEntityCreatePermission() {
    // Handled by the admin permission.
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function getEntityDeletePermission($scope = 'any') {
    // Handled by the admin permission.
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function getEntityViewPermission($scope = 'any') {
    // Handled by the admin permission.
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function getEntityUpdatePermission($scope = 'any') {
    // Handled by the admin permission.
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function getRelationViewPermission($scope = 'any') {
    // Handled by the admin permission.
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function getRelationUpdatePermission($scope = 'any') {
    // Handled by the admin permission.
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function getRelationDeletePermission($scope = 'any') {
    // Handled by the admin permission.
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function getRelationCreatePermission($scope = 'any') {
    // Handled by the admin permission.
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function buildPermissions() {
    $permissions = parent::buildPermissions();

    $permissions['invite users to group'] = [
      'title' => 'Invite users to group',
      'description' => 'Allows users with permissions to invite new users to group.',
    ];
    $permissions['view group invitations'] = [
      'title' => "View group invitations",
      'description' => 'Allows users with permissions view created invitations.',
    ];
    $permissions['delete own invitations'] = [
      'title' => 'Delete own invitations',
      'description' => 'Allows users with permissions to delete own invitations to group.',
    ];
    $permissions['delete any invitation'] = [
      'title' => 'Delete any invitation',
      'description' => 'Allows users with permissions to delete any invitation to group.',
    ];

    return $permissions;
  }

}
