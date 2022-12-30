<?php

namespace Drupal\group\ConfigTranslation;

use Drupal\config_translation\ConfigEntityMapper;

/**
 * Provides a configuration mapper for group roles.
 */
class GroupRoleMapper extends ConfigEntityMapper {

  /**
   * {@inheritdoc}
   */
  public function getBaseRouteParameters() {
    return [
      $this->entityType => $this->entity->id(),
      'group_type' => $this->getEntity()->get('group_type')
    ];
  }

}
